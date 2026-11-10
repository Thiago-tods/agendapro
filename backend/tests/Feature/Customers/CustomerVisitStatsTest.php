<?php

namespace Tests\Feature\Customers;

use App\Modules\Appointments\Enums\AppointmentStatus;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Customers\Models\Customer;
use App\Modules\Professionals\Models\Professional;
use App\Modules\Services\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsCompanyMember;
use Tests\TestCase;

class CustomerVisitStatsTest extends TestCase
{
    use ActsAsCompanyMember, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCompanies();
        CarbonImmutable::setTestNow('2026-11-10 12:00:00');
    }

    private function appointment(Customer $customer, string $utcStart, AppointmentStatus $status): void
    {
        $service = Service::factory()->for($this->company)->create();
        $professional = Professional::factory()->for($this->company)->create();
        $start = CarbonImmutable::parse($utcStart, 'UTC');

        Appointment::create([
            'company_id' => $this->company->id,
            'customer_id' => $customer->id,
            'professional_id' => $professional->id,
            'service_id' => $service->id,
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
            'occupied_until' => $start->addHour(),
            'status' => $status,
            'service_name' => $service->name,
            'service_price_cents' => 1000,
            'service_duration_minutes' => 60,
            'service_buffer_minutes' => 0,
            'cancelled_at' => $status === AppointmentStatus::Cancelled ? now() : null,
        ]);
    }

    public function test_list_and_profile_include_completed_visits_and_next_booking(): void
    {
        $this->actAs(CompanyRole::Professional);
        $customer = Customer::factory()->for($this->company)->create();
        $this->appointment($customer, '2026-10-01 13:00', AppointmentStatus::Completed);
        $this->appointment($customer, '2026-11-02 13:00', AppointmentStatus::Completed);
        $this->appointment($customer, '2026-11-05 13:00', AppointmentStatus::NoShow);
        $this->appointment($customer, '2026-11-12 13:00', AppointmentStatus::Cancelled);
        $this->appointment($customer, '2026-11-20 13:00', AppointmentStatus::Confirmed);

        $this->getJson($this->companyUrl('/customers'))
            ->assertOk()
            ->assertJsonPath('data.0.visits_count', 2)
            ->assertJsonPath('data.0.last_visit_at', '2026-11-02T13:00:00+00:00')
            ->assertJsonPath('data.0.next_appointment_at', '2026-11-20T13:00:00+00:00');

        $this->getJson($this->companyUrl("/customers/{$customer->id}"))
            ->assertOk()
            ->assertJsonPath('data.visits_count', 2)
            ->assertJsonPath('data.next_appointment_at', '2026-11-20T13:00:00+00:00');
    }

    public function test_customer_without_history_has_zero_visits(): void
    {
        $this->actAs(CompanyRole::Receptionist);
        Customer::factory()->for($this->company)->create();

        $this->getJson($this->companyUrl('/customers'))
            ->assertJsonPath('data.0.visits_count', 0)
            ->assertJsonPath('data.0.last_visit_at', null);
    }
}
