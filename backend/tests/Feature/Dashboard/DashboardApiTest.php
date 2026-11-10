<?php

namespace Tests\Feature\Dashboard;

use App\Modules\Appointments\Enums\AppointmentStatus;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Models\Customer;
use App\Modules\Professionals\Models\Professional;
use App\Modules\Services\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsSchedule;
use Tests\TestCase;

/**
 * "Now" is Friday 2026-11-13 09:00 in São Paulo (12:00 UTC); see BuildsSchedule.
 * Current period: 2026-10-15 .. 2026-11-13 (30 local days). Previous: 2026-09-15 .. 2026-10-14.
 */
class DashboardApiTest extends TestCase
{
    use BuildsSchedule, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchedule();
    }

    /** Inserts an appointment directly, bypassing booking rules, to build history. */
    private function appointment(string $localStart, AppointmentStatus $status, int $price = 10000, ?Company $company = null, ?Professional $professional = null): Appointment
    {
        $company ??= $this->company;
        $startsAt = CarbonImmutable::parse($localStart, 'America/Sao_Paulo')->utc();
        $customer = $company->is($this->company) ? $this->customer : Customer::factory()->for($company)->create();
        $service = $company->is($this->company) ? $this->service : Service::factory()->for($company)->create();
        $professional ??= $company->is($this->company) ? $this->professional : Professional::factory()->for($company)->create();

        return Appointment::create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'occupied_until' => $startsAt->addHour(),
            'status' => $status,
            'service_name' => $service->name,
            'service_price_cents' => $price,
            'service_duration_minutes' => 60,
            'service_buffer_minutes' => 0,
            'cancelled_at' => $status === AppointmentStatus::Cancelled ? now() : null,
        ]);
    }

    private function dashboard(): TestResponse
    {
        return $this->getJson($this->companyUrl('/dashboard'))->assertOk();
    }

    public function test_empty_company_returns_zeros_and_null_rates(): void
    {
        $this->actAs(CompanyRole::Owner);

        $this->dashboard()
            ->assertJsonPath('data.today.total', 0)
            ->assertJsonPath('data.period.current.attendance_rate', null)
            ->assertJsonPath('data.period.current.revenue_cents', 0)
            ->assertJsonCount(8, 'data.weekly_trend')
            ->assertJsonCount(0, 'data.upcoming')
            ->assertJsonCount(0, 'data.recent_activity')
            ->assertJsonCount(0, 'data.top_services');
    }

    public function test_today_counts_use_the_company_local_day(): void
    {
        $this->actAs(CompanyRole::Receptionist);

        $this->appointment('2026-11-13 08:00', AppointmentStatus::Completed);
        $this->appointment('2026-11-13 08:30', AppointmentStatus::NoShow);
        $this->appointment('2026-11-13 10:00', AppointmentStatus::Confirmed);
        $this->appointment('2026-11-13 14:00', AppointmentStatus::Scheduled);
        $this->appointment('2026-11-13 15:00', AppointmentStatus::Cancelled);
        // 23:30 local on the 13th is already the 14th in UTC: still "today" locally.
        $this->appointment('2026-11-13 23:30', AppointmentStatus::Scheduled);
        // 00:30 local on the 13th... belongs to today; 23:30 on the 12th does not.
        $this->appointment('2026-11-12 23:30', AppointmentStatus::Completed);
        // Future day still waiting for confirmation.
        $this->appointment('2026-11-20 10:00', AppointmentStatus::Scheduled);

        $this->dashboard()
            ->assertJsonPath('data.today.total', 5)
            ->assertJsonPath('data.today.completed', 1)
            ->assertJsonPath('data.today.no_show', 1)
            ->assertJsonPath('data.today.cancelled', 1)
            ->assertJsonPath('data.today.remaining', 3)
            ->assertJsonPath('data.today.awaiting_confirmation', 3);
    }

    public function test_period_metrics_revenue_from_snapshots_and_attendance_rate(): void
    {
        $this->actAs(CompanyRole::Owner);

        $this->appointment('2026-11-10 10:00', AppointmentStatus::Completed, 12000);
        $this->appointment('2026-10-15 09:00', AppointmentStatus::Completed, 8000);  // first day of the period
        $this->appointment('2026-11-03 10:00', AppointmentStatus::NoShow, 9999);    // no revenue
        $this->appointment('2026-11-04 10:00', AppointmentStatus::Cancelled, 5000); // not booked
        $this->appointment('2026-10-14 10:00', AppointmentStatus::Completed, 7000); // previous period

        // Catalog price changes later must not affect revenue.
        $this->service->update(['price_cents' => 1]);

        $this->dashboard()
            ->assertJsonPath('data.period.from', '2026-10-15')
            ->assertJsonPath('data.period.to', '2026-11-13')
            ->assertJsonPath('data.period.current.booked', 3)
            ->assertJsonPath('data.period.current.completed', 2)
            ->assertJsonPath('data.period.current.no_show', 1)
            ->assertJsonPath('data.period.current.cancelled', 1)
            ->assertJsonPath('data.period.current.revenue_cents', 20000)
            ->assertJsonPath('data.period.current.attendance_rate', 0.6667)
            ->assertJsonPath('data.period.previous.revenue_cents', 7000)
            ->assertJsonPath('data.period.previous.attendance_rate', 1);
    }

    public function test_weekly_trend_buckets_by_local_monday_weeks(): void
    {
        $this->actAs(CompanyRole::Owner);

        $this->appointment('2026-11-09 10:00', AppointmentStatus::Completed, 5000); // Monday of current week
        $this->appointment('2026-11-08 22:30', AppointmentStatus::Completed, 3000); // Sunday local (Monday UTC): previous week
        $this->appointment('2026-11-12 10:00', AppointmentStatus::Scheduled);

        $trend = $this->dashboard()->json('data.weekly_trend');

        $this->assertSame('2026-09-21', $trend[0]['week_start']);
        $this->assertSame(['week_start' => '2026-11-09', 'booked' => 2, 'completed' => 1, 'revenue_cents' => 5000], $trend[7]);
        $this->assertSame(['week_start' => '2026-11-02', 'booked' => 1, 'completed' => 1, 'revenue_cents' => 3000], $trend[6]);
    }

    public function test_financial_data_is_hidden_from_roles_without_permission(): void
    {
        $this->appointment('2026-11-10 10:00', AppointmentStatus::Completed, 12000);

        foreach ([CompanyRole::Receptionist, CompanyRole::Professional] as $role) {
            $this->actAs($role);

            $response = $this->dashboard()
                ->assertJsonPath('data.period.current.completed', 1)
                ->assertJsonPath('data.period.current.revenue_cents', null)
                ->assertJsonPath('data.weekly_trend.7.revenue_cents', null)
                ->assertJsonPath('data.top_services.0.revenue_cents', null);

            $this->assertStringNotContainsString('12000', (string) $response->getContent());
        }
    }

    public function test_upcoming_lists_active_future_and_running_appointments_in_order(): void
    {
        $this->actAs(CompanyRole::Professional);

        $this->appointment('2026-11-13 08:30', AppointmentStatus::CheckedIn);  // running until 09:30
        $this->appointment('2026-11-13 07:00', AppointmentStatus::Confirmed);  // already over
        $this->appointment('2026-11-13 11:00', AppointmentStatus::Cancelled);
        $later = $this->appointment('2026-11-16 10:00', AppointmentStatus::Scheduled);
        $soon = $this->appointment('2026-11-13 10:00', AppointmentStatus::Confirmed);

        $this->dashboard()
            ->assertJsonCount(3, 'data.upcoming')
            ->assertJsonPath('data.upcoming.0.status', 'checked_in')
            ->assertJsonPath('data.upcoming.1.id', $soon->id)
            ->assertJsonPath('data.upcoming.2.id', $later->id)
            ->assertJsonPath('data.upcoming.1.customer.name', $this->customer->name)
            ->assertJsonPath('data.upcoming.1.local.start', '10:00');
    }

    public function test_recent_activity_comes_from_appointment_events(): void
    {
        $user = $this->actAs(CompanyRole::Receptionist);
        $id = $this->bookOk(self::MONDAY.'T10:00');
        $this->postJson($this->companyUrl("/appointments/{$id}/confirm"))->assertOk();

        $this->dashboard()
            ->assertJsonCount(2, 'data.recent_activity')
            ->assertJsonPath('data.recent_activity.0.type', 'confirmed')
            ->assertJsonPath('data.recent_activity.0.actor', $user->name)
            ->assertJsonPath('data.recent_activity.0.appointment.customer', $this->customer->name)
            ->assertJsonPath('data.recent_activity.0.appointment.local_start', '2026-11-16 10:00')
            ->assertJsonPath('data.recent_activity.1.type', 'created');
    }

    public function test_rankings_of_services_and_professionals(): void
    {
        $this->actAs(CompanyRole::Admin);
        $second = $this->makeProfessional(['name' => 'Bruna']);

        $this->appointment('2026-11-02 10:00', AppointmentStatus::Completed);
        $this->appointment('2026-11-03 10:00', AppointmentStatus::Completed);
        $this->appointment('2026-11-04 10:00', AppointmentStatus::NoShow);
        $this->appointment('2026-11-04 10:00', AppointmentStatus::Completed, professional: $second);

        $this->dashboard()
            ->assertJsonPath('data.top_services.0.name', 'Corte')
            ->assertJsonPath('data.top_services.0.completed', 3)
            ->assertJsonPath('data.top_services.0.revenue_cents', 30000)
            ->assertJsonPath('data.top_professionals.0.id', $this->professional->id)
            ->assertJsonPath('data.top_professionals.0.completed', 2)
            ->assertJsonPath('data.top_professionals.0.attendance_rate', 0.6667)
            ->assertJsonPath('data.top_professionals.1.name', 'Bruna');
    }

    public function test_other_companies_data_never_leaks_into_the_dashboard(): void
    {
        $this->actAs(CompanyRole::Owner);
        $this->appointment('2026-11-13 10:00', AppointmentStatus::Confirmed, 50000, $this->otherCompany);
        $this->appointment('2026-11-10 10:00', AppointmentStatus::Completed, 50000, $this->otherCompany);

        $this->dashboard()
            ->assertJsonPath('data.today.total', 0)
            ->assertJsonPath('data.period.current.revenue_cents', 0)
            ->assertJsonCount(0, 'data.upcoming')
            ->assertJsonCount(0, 'data.top_services');

        $this->getJson($this->companyUrl('/dashboard', $this->otherCompany))->assertNotFound();
    }

    public function test_guests_cannot_see_the_dashboard(): void
    {
        $this->getJson($this->companyUrl('/dashboard'))->assertUnauthorized();
    }
}
