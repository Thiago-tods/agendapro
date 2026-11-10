<?php

namespace Tests\Feature\Appointments;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Professionals\Models\Professional;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsSchedule;
use Tests\TestCase;

class AppointmentLifecycleTest extends TestCase
{
    use BuildsSchedule, RefreshDatabase;

    private string $id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchedule();
        $this->actAs(CompanyRole::Receptionist);
        $this->id = $this->bookOk(self::MONDAY.'T10:00'); // 13:00Z-14:00Z, occupied until 14:15Z
    }

    private function act(string $action, array $payload = []): TestResponse
    {
        return $this->postJson($this->companyUrl("/appointments/{$this->id}/{$action}"), $payload);
    }

    private function travelToLocal(string $local): void
    {
        $this->travelTo(CarbonImmutable::parse($local, 'America/Sao_Paulo'));
    }

    // --- Confirm ------------------------------------------------------------------------

    public function test_confirm_records_status_and_event(): void
    {
        $this->act('confirm')
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.events.1.type', 'confirmed')
            ->assertJsonPath('data.events.1.from_status', 'scheduled')
            ->assertJsonPath('data.events.1.to_status', 'confirmed');
    }

    // --- Reschedule ---------------------------------------------------------------------

    public function test_reschedule_moves_the_interval_keeps_the_snapshot_and_records_from_and_to(): void
    {
        $this->act('confirm');
        $this->service->update(['duration_minutes' => 120, 'price_cents' => 1]);

        $this->act('reschedule', ['starts_at' => self::MONDAY.'T15:00', 'reason' => 'Pedido do cliente'])
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled') // confirmation was for the old time
            ->assertJsonPath('data.local.start', '15:00')
            ->assertJsonPath('data.ends_at', '2026-11-16T19:00:00Z') // snapshot duration (60), not 120
            ->assertJsonPath('data.service.price_cents', 12000)
            ->assertJsonPath('data.events.2.type', 'rescheduled')
            ->assertJsonPath('data.events.2.reason', 'Pedido do cliente')
            ->assertJsonPath('data.events.2.changes.from.starts_at', '2026-11-16T13:00:00Z')
            ->assertJsonPath('data.events.2.changes.to.starts_at', '2026-11-16T18:00:00Z');

        // The old slot is free again.
        $this->book(self::MONDAY.'T10:00')->assertCreated();
    }

    public function test_reschedule_may_overlap_its_own_previous_interval(): void
    {
        $this->act('reschedule', ['starts_at' => self::MONDAY.'T10:15'])->assertOk();
    }

    public function test_reschedule_into_a_conflict_is_rejected_and_nothing_changes(): void
    {
        $this->bookOk(self::MONDAY.'T14:00');

        $this->act('reschedule', ['starts_at' => self::MONDAY.'T14:30'])->assertStatus(409);

        $this->assertSame('2026-11-16T13:00:00Z', Appointment::find($this->id)->starts_at->toIso8601ZuluString());
        $this->assertDatabaseCount('appointment_events', 2); // only the two "created" events
    }

    public function test_reschedule_respects_hours_and_blocks(): void
    {
        $this->act('reschedule', ['starts_at' => self::MONDAY.'T12:00'])->assertUnprocessable();
        $this->act('reschedule', ['starts_at' => '2026-11-15T10:00'])->assertUnprocessable();
    }

    public function test_reschedule_to_another_professional_requires_the_service(): void
    {
        $unqualified = Professional::factory()->for($this->company)->create();
        $this->act('reschedule', ['starts_at' => self::MONDAY.'T10:00', 'professional_id' => $unqualified->id])
            ->assertJsonValidationErrors('professional_id');

        $qualified = $this->makeProfessional();
        $this->act('reschedule', ['starts_at' => self::MONDAY.'T10:00', 'professional_id' => $qualified->id])
            ->assertOk()
            ->assertJsonPath('data.professional.id', $qualified->id)
            ->assertJsonPath('data.events.1.changes.from.professional_id', $this->professional->id);
    }

    public function test_reschedule_requires_a_change(): void
    {
        $this->act('reschedule', ['starts_at' => self::MONDAY.'T10:00'])->assertJsonValidationErrors('starts_at');
    }

    // --- Cancel -------------------------------------------------------------------------

    public function test_cancel_requires_a_reason_and_records_actor_and_time(): void
    {
        $this->act('cancel')->assertJsonValidationErrors('reason');

        $this->act('cancel', ['reason' => 'Cliente viajou'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Cliente viajou')
            ->assertJsonPath('data.cancelled_at', '2026-11-13T12:00:00Z')
            ->assertJsonPath('data.events.1.type', 'cancelled')
            ->assertJsonPath('data.events.1.actor.id', auth()->id());

        $this->assertDatabaseHas('appointments', ['id' => $this->id, 'cancelled_by' => auth()->id()]);
    }

    public function test_cancelled_appointment_is_final(): void
    {
        $this->act('cancel', ['reason' => 'Imprevisto']);

        $this->act('cancel', ['reason' => 'De novo'])->assertJsonValidationErrors('status');
        $this->act('confirm')->assertJsonValidationErrors('status');
        $this->act('reschedule', ['starts_at' => self::MONDAY.'T15:00'])->assertJsonValidationErrors('status');
    }

    public function test_appointments_cannot_be_deleted(): void
    {
        $this->deleteJson($this->companyUrl("/appointments/{$this->id}"))->assertStatus(405);

        $this->expectException(\LogicException::class);
        Appointment::find($this->id)->delete();
    }

    // --- Check-in, completion, no-show --------------------------------------------------

    public function test_check_in_window_and_completion(): void
    {
        $this->act('complete')->assertJsonValidationErrors('status'); // must check in first
        $this->act('check-in')->assertJsonValidationErrors('status'); // Friday, too early

        $this->travelToLocal(self::MONDAY.' 08:00');
        $this->act('check-in')->assertOk()->assertJsonPath('data.status', 'checked_in')
            ->assertJsonPath('data.checked_in_at', '2026-11-16T11:00:00Z');

        $this->act('cancel', ['reason' => 'Tarde demais'])->assertJsonValidationErrors('status');

        $this->travelToLocal(self::MONDAY.' 11:00');
        $this->act('complete')->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.completed_at', '2026-11-16T14:00:00Z');
    }

    public function test_completed_appointment_still_occupies_its_slot(): void
    {
        $this->travelToLocal(self::MONDAY.' 10:00');
        $this->act('check-in');
        $this->act('complete');

        $this->travelToLocal(self::MONDAY.' 09:00');
        $this->book(self::MONDAY.'T10:00')->assertStatus(409);
    }

    public function test_check_in_after_the_slot_ended_is_rejected(): void
    {
        $this->travelToLocal(self::MONDAY.' 11:15');

        $this->act('check-in')->assertJsonValidationErrors('status');
    }

    public function test_no_show_only_after_start_time(): void
    {
        $this->act('no-show')->assertJsonValidationErrors('status');

        $this->travelToLocal(self::MONDAY.' 10:20');
        $this->act('no-show', ['reason' => 'Não atendeu o telefone'])
            ->assertOk()
            ->assertJsonPath('data.status', 'no_show')
            ->assertJsonPath('data.events.1.reason', 'Não atendeu o telefone');
    }

    // --- Authorization ------------------------------------------------------------------

    public function test_professional_role_cannot_change_appointments(): void
    {
        $this->actAs(CompanyRole::Professional);

        $this->act('confirm')->assertForbidden();
        $this->act('cancel', ['reason' => 'Teste'])->assertForbidden();
        $this->act('reschedule', ['starts_at' => self::MONDAY.'T15:00'])->assertForbidden();
    }

    // --- Customer history and record protection ----------------------------------------

    public function test_customer_history_lists_appointments_newest_first(): void
    {
        $this->bookOk('2026-11-17T10:00');

        $this->getJson($this->companyUrl("/customers/{$this->customer->id}/appointments"))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.local.date', '2026-11-17');
    }

    public function test_records_with_appointments_cannot_be_hard_deleted(): void
    {
        $this->actAs(CompanyRole::Owner);

        $this->deleteJson($this->companyUrl("/customers/{$this->customer->id}"))->assertStatus(409);
        $this->deleteJson($this->companyUrl("/professionals/{$this->professional->id}"))->assertStatus(409);
        $this->deleteJson($this->companyUrl("/services/{$this->service->id}"))->assertStatus(409);
    }
}
