<?php

namespace Tests\Feature\Appointments;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Availability\Models\AvailabilityException;
use App\Modules\Availability\Models\BlockedPeriod;
use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Customers\Models\Customer;
use App\Modules\Professionals\Models\Professional;
use App\Modules\Services\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsSchedule;
use Tests\TestCase;

class AppointmentBookingTest extends TestCase
{
    use BuildsSchedule, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchedule();
        $this->actAs(CompanyRole::Receptionist);
    }

    // --- Creation, snapshot and time storage --------------------------------------------

    public function test_books_with_service_snapshot_utc_storage_and_creation_event(): void
    {
        $response = $this->book(self::MONDAY.'T10:00')->assertCreated();

        $response
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.starts_at', '2026-11-16T13:00:00Z')
            ->assertJsonPath('data.ends_at', '2026-11-16T14:00:00Z')
            ->assertJsonPath('data.occupied_until', '2026-11-16T14:15:00Z')
            ->assertJsonPath('data.local.date', self::MONDAY)
            ->assertJsonPath('data.local.start', '10:00')
            ->assertJsonPath('data.local.end', '11:00')
            ->assertJsonPath('data.service.name', 'Corte')
            ->assertJsonPath('data.service.price_cents', 12000)
            ->assertJsonPath('data.service.duration_minutes', 60)
            ->assertJsonPath('data.service.buffer_minutes', 15)
            ->assertJsonPath('data.events.0.type', 'created')
            ->assertJsonPath('data.events.0.actor.id', auth()->id());

        $raw = DB::selectOne("select to_char(starts_at at time zone 'UTC', 'YYYY-MM-DD HH24:MI') as utc from appointments");
        $this->assertSame('2026-11-16 13:00', $raw->utc);
    }

    public function test_accepts_an_explicit_utc_offset(): void
    {
        $this->book('2026-11-16T13:00:00Z')->assertCreated()->assertJsonPath('data.local.start', '10:00');
    }

    public function test_snapshot_is_not_affected_by_later_catalog_changes(): void
    {
        $id = $this->bookOk(self::MONDAY.'T10:00');

        $this->service->update(['name' => 'Corte Premium', 'price_cents' => 99900, 'duration_minutes' => 90, 'buffer_minutes' => 0]);

        $this->getJson($this->companyUrl("/appointments/{$id}"))
            ->assertJsonPath('data.service.name', 'Corte')
            ->assertJsonPath('data.service.price_cents', 12000)
            ->assertJsonPath('data.service.duration_minutes', 60)
            ->assertJsonPath('data.ends_at', '2026-11-16T14:00:00Z');
    }

    // --- Conflicts (half-open intervals, buffer) ----------------------------------------

    public function test_overlapping_booking_for_the_same_professional_is_rejected(): void
    {
        $this->bookOk(self::MONDAY.'T10:00');

        $this->book(self::MONDAY.'T10:30')->assertStatus(409);
        $this->book(self::MONDAY.'T09:30')->assertStatus(409); // 09:30-10:45 occupied overlaps 10:00
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_buffer_is_part_of_the_occupied_interval(): void
    {
        $this->bookOk(self::MONDAY.'T14:00'); // service 14:00-15:00, occupied until 15:15

        $this->book(self::MONDAY.'T15:00')->assertStatus(409);
        $this->book(self::MONDAY.'T15:10')->assertStatus(409);
    }

    public function test_half_open_intervals_allow_back_to_back_bookings(): void
    {
        $this->bookOk(self::MONDAY.'T14:00'); // occupied [14:00, 15:15)

        $this->book(self::MONDAY.'T15:15')->assertCreated(); // starts exactly when the first ends
    }

    public function test_previous_booking_ending_exactly_at_start_does_not_conflict(): void
    {
        $this->bookOk(self::MONDAY.'T10:45'); // occupied [10:45, 12:00)

        $this->book(self::MONDAY.'T09:30')->assertCreated(); // occupied [09:30, 10:45)
    }

    public function test_other_professionals_are_independent(): void
    {
        $this->bookOk(self::MONDAY.'T10:00');
        $other = $this->makeProfessional();

        $this->book(self::MONDAY.'T10:00', ['professional_id' => $other->id])->assertCreated();
    }

    public function test_cancelled_and_no_show_appointments_free_the_slot(): void
    {
        $id = $this->bookOk(self::MONDAY.'T10:00');
        $this->postJson($this->companyUrl("/appointments/{$id}/cancel"), ['reason' => 'Cliente desmarcou'])->assertOk();

        $this->book(self::MONDAY.'T10:00')->assertCreated();
    }

    // --- Schedule limits ----------------------------------------------------------------

    /** @return array<string, array{string}> */
    public static function outsideBusinessHours(): array
    {
        return [
            'before opening' => [self::MONDAY.'T08:00'],
            'starts before opening' => [self::MONDAY.'T08:30'],
            'runs past closing' => [self::MONDAY.'T17:30'],
            'runs into lunch break' => [self::MONDAY.'T11:30'],
            'inside lunch break' => [self::MONDAY.'T12:00'],
            'after closing' => [self::MONDAY.'T18:00'],
            'sunday' => ['2026-11-15T10:00'],
            'saturday' => ['2026-11-14T10:00'],
        ];
    }

    #[DataProvider('outsideBusinessHours')]
    public function test_rejects_bookings_outside_business_hours(string $start): void
    {
        $this->book($start)->assertUnprocessable()->assertJsonValidationErrors('starts_at');
    }

    /** @return array<string, array{string}> */
    public static function edgesInsideBusinessHours(): array
    {
        return [
            'opening time' => [self::MONDAY.'T09:00'],
            'ends exactly at lunch' => [self::MONDAY.'T11:00'],
            'right after lunch' => [self::MONDAY.'T13:00'],
            'ends exactly at closing' => [self::MONDAY.'T17:00'], // buffer may extend past closing
        ];
    }

    #[DataProvider('edgesInsideBusinessHours')]
    public function test_accepts_bookings_on_the_edges_of_business_hours(string $start): void
    {
        $this->book($start)->assertCreated();
    }

    public function test_professional_working_hours_narrow_the_company_hours(): void
    {
        $this->weekly($this->professional->id, 1, '14:00', '20:00');

        $this->book(self::MONDAY.'T10:00')->assertUnprocessable();
        $this->book(self::MONDAY.'T17:30')->assertUnprocessable(); // professional yes, company closes at 18:00
        $this->book(self::MONDAY.'T14:00')->assertCreated();
    }

    public function test_professional_with_hours_on_other_days_is_unavailable_on_unlisted_days(): void
    {
        $this->weekly($this->professional->id, 2, '09:00', '18:00'); // Tuesdays only

        $this->book(self::MONDAY.'T10:00')->assertUnprocessable();
    }

    public function test_company_closed_exception_blocks_the_whole_day(): void
    {
        AvailabilityException::create([
            'company_id' => $this->company->id, 'date' => self::MONDAY, 'is_closed' => true, 'reason' => 'Feriado',
        ]);

        $this->book(self::MONDAY.'T10:00')->assertUnprocessable();
    }

    public function test_exception_hours_replace_the_weekly_hours_for_that_date(): void
    {
        // Company opens on Saturday only for this date.
        AvailabilityException::create([
            'company_id' => $this->company->id, 'date' => '2026-11-14', 'is_closed' => false,
            'start_time' => '08:00', 'end_time' => '12:00',
        ]);
        // Professional leaves early on Monday.
        AvailabilityException::create([
            'company_id' => $this->company->id, 'professional_id' => $this->professional->id,
            'date' => self::MONDAY, 'is_closed' => false, 'start_time' => '09:00', 'end_time' => '11:00',
        ]);

        $this->book('2026-11-14T08:00')->assertCreated();
        $this->book('2026-11-14T11:30')->assertUnprocessable();
        $this->book(self::MONDAY.'T10:00')->assertCreated();
        $this->book(self::MONDAY.'T13:00')->assertUnprocessable();
    }

    public function test_professional_exception_cannot_open_a_day_the_company_is_closed(): void
    {
        AvailabilityException::create([
            'company_id' => $this->company->id, 'professional_id' => $this->professional->id,
            'date' => '2026-11-15', 'is_closed' => false, 'start_time' => '09:00', 'end_time' => '18:00',
        ]);

        $this->book('2026-11-15T10:00')->assertUnprocessable();
    }

    public function test_blocked_periods_reject_overlapping_bookings(): void
    {
        BlockedPeriod::create([
            'company_id' => $this->company->id, 'professional_id' => $this->professional->id,
            'starts_at' => CarbonImmutable::parse(self::MONDAY.' 10:00', 'America/Sao_Paulo')->utc(),
            'ends_at' => CarbonImmutable::parse(self::MONDAY.' 11:00', 'America/Sao_Paulo')->utc(),
            'reason' => 'Reunião',
        ]);

        $this->book(self::MONDAY.'T10:30')->assertUnprocessable();
        $this->book(self::MONDAY.'T09:00')->assertUnprocessable(); // buffer reaches into the block
        $this->book(self::MONDAY.'T11:00')->assertCreated();       // starts exactly when the block ends
    }

    public function test_company_wide_block_applies_to_every_professional(): void
    {
        BlockedPeriod::create([
            'company_id' => $this->company->id,
            'starts_at' => CarbonImmutable::parse(self::MONDAY.' 09:00', 'America/Sao_Paulo')->utc(),
            'ends_at' => CarbonImmutable::parse(self::MONDAY.' 18:00', 'America/Sao_Paulo')->utc(),
        ]);

        $this->book(self::MONDAY.'T10:00')->assertUnprocessable();
        $this->book(self::MONDAY.'T10:00', ['professional_id' => $this->makeProfessional()->id])->assertUnprocessable();
    }

    public function test_rejects_past_and_misaligned_start_times(): void
    {
        $this->book('2026-11-13T08:00')->assertUnprocessable()->assertJsonValidationErrors('starts_at'); // yesterday-ish (past)
        $this->book(self::MONDAY.'T10:07')->assertUnprocessable()->assertJsonValidationErrors('starts_at');
    }

    public function test_company_without_business_hours_cannot_take_bookings(): void
    {
        $this->company->workingHours()->delete();

        $this->book(self::MONDAY.'T10:00')->assertUnprocessable();
    }

    // --- Booking parties ----------------------------------------------------------------

    public function test_rejects_inactive_professional_inactive_service_archived_customer_and_unqualified_professional(): void
    {
        $inactive = $this->makeProfessional(['is_active' => false]);
        $this->book(self::MONDAY.'T10:00', ['professional_id' => $inactive->id])->assertJsonValidationErrors('professional_id');

        $unqualified = Professional::factory()->for($this->company)->create();
        $this->book(self::MONDAY.'T10:00', ['professional_id' => $unqualified->id])->assertJsonValidationErrors('professional_id');

        $archived = Customer::factory()->for($this->company)->archived()->create();
        $this->book(self::MONDAY.'T10:00', ['customer_id' => $archived->id])->assertJsonValidationErrors('customer_id');

        $this->service->update(['is_active' => false]);
        $this->book(self::MONDAY.'T10:00')->assertJsonValidationErrors('service_id');

        $this->assertDatabaseCount('appointments', 0);
    }

    // --- Concurrency --------------------------------------------------------------------

    public function test_database_exclusion_constraint_rejects_overlaps_even_without_the_application(): void
    {
        $this->bookOk(self::MONDAY.'T10:00');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('appointments_no_overlap');

        DB::table('appointments')->insert($this->rawAppointment('2026-11-16 13:30:00+00', '2026-11-16 14:45:00+00'));
    }

    public function test_exclusion_constraint_ignores_cancelled_rows(): void
    {
        $id = $this->bookOk(self::MONDAY.'T10:00');
        $this->postJson($this->companyUrl("/appointments/{$id}/cancel"), ['reason' => 'Imprevisto'])->assertOk();

        DB::table('appointments')->insert($this->rawAppointment('2026-11-16 13:00:00+00', '2026-11-16 14:15:00+00'));

        $this->assertDatabaseCount('appointments', 2);
    }

    /**
     * Simulates the race: another transaction commits a conflicting booking after our
     * availability check passed but before our insert. The constraint must stop it and
     * the API must answer 409 without persisting anything from the failed request.
     */
    public function test_race_between_check_and_insert_is_caught_by_the_database_and_returns_409(): void
    {
        Appointment::creating(function (): void {
            DB::table('appointments')->insert($this->rawAppointment('2026-11-16 13:00:00+00', '2026-11-16 14:15:00+00'));
        });

        try {
            $this->book(self::MONDAY.'T10:00')
                ->assertStatus(409)
                ->assertJsonPath('message', 'O profissional já possui um agendamento neste horário.');
        } finally {
            Appointment::flushEventListeners();
        }

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseCount('appointment_events', 0);
    }

    // --- Authorization and isolation ----------------------------------------------------

    public function test_professional_role_can_view_but_not_book(): void
    {
        $id = $this->bookOk(self::MONDAY.'T10:00');
        $this->actAs(CompanyRole::Professional);

        $this->book(self::MONDAY.'T14:00')->assertForbidden();
        $this->getJson($this->companyUrl("/appointments/{$id}"))->assertOk();
        $this->getJson($this->companyUrl('/appointments?from='.self::MONDAY.'&to='.self::MONDAY))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_cannot_book_with_records_of_another_company(): void
    {
        $foreignCustomer = Customer::factory()->for($this->otherCompany)->create();
        $foreignService = Service::factory()->for($this->otherCompany)->create();
        $foreignProfessional = Professional::factory()->for($this->otherCompany)->create();

        $this->book(self::MONDAY.'T10:00', [
            'customer_id' => $foreignCustomer->id,
            'service_id' => $foreignService->id,
            'professional_id' => $foreignProfessional->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['customer_id', 'service_id', 'professional_id']);
    }

    public function test_another_companys_appointment_is_not_found(): void
    {
        $id = $this->bookOk(self::MONDAY.'T10:00');
        $this->otherCompany->members()->create(['user_id' => auth()->id(), 'role' => CompanyRole::Owner]);

        $this->getJson($this->companyUrl("/appointments/{$id}", $this->otherCompany))->assertNotFound();
        $this->postJson($this->companyUrl("/appointments/{$id}/cancel", $this->otherCompany), ['reason' => 'xxx'])->assertNotFound();
    }

    public function test_list_filters_by_local_date_range_and_professional(): void
    {
        $this->bookOk(self::MONDAY.'T10:00');
        $other = $this->makeProfessional();
        $this->bookOk(self::MONDAY.'T10:00', ['professional_id' => $other->id]);
        $this->bookOk('2026-11-17T17:00'); // Tuesday 17:00 local = 20:00 UTC

        $this->getJson($this->companyUrl('/appointments?from='.self::MONDAY.'&to='.self::MONDAY))->assertJsonCount(2, 'data');
        $this->getJson($this->companyUrl('/appointments?from=2026-11-17&to=2026-11-17'))->assertJsonCount(1, 'data');
        $this->getJson($this->companyUrl("/appointments?from=2026-11-16&to=2026-11-17&professional_id={$other->id}"))->assertJsonCount(1, 'data');
        $this->getJson($this->companyUrl('/appointments?from=2026-01-01&to=2026-12-31'))->assertUnprocessable();
    }

    /** @return array<string, mixed> */
    private function rawAppointment(string $start, string $occupiedUntil): array
    {
        return [
            'id' => (string) Str::uuid(),
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'professional_id' => $this->professional->id,
            'service_id' => $this->service->id,
            'starts_at' => $start,
            'ends_at' => $start === $occupiedUntil ? $start : CarbonImmutable::parse($occupiedUntil)->subMinutes(15),
            'occupied_until' => $occupiedUntil,
            'status' => 'scheduled',
            'service_name' => 'Corte',
            'service_price_cents' => 12000,
            'service_duration_minutes' => 60,
            'service_buffer_minutes' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
