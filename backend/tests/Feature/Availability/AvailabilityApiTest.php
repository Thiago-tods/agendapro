<?php

namespace Tests\Feature\Availability;

use App\Modules\Availability\Models\AvailabilityException;
use App\Modules\Availability\Models\BlockedPeriod;
use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Professionals\Models\Professional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchedule;
use Tests\TestCase;

class AvailabilityApiTest extends TestCase
{
    use BuildsSchedule, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchedule();
    }

    public function test_business_hours_are_replaced_as_a_whole(): void
    {
        $this->actAs(CompanyRole::Admin);

        $this->putJson($this->companyUrl('/business-hours'), ['periods' => [
            ['weekday' => 6, 'start_time' => '08:00', 'end_time' => '12:00'],
            ['weekday' => 6, 'start_time' => '12:00', 'end_time' => '14:00'],
        ]])->assertOk()->assertJsonCount(2, 'data.periods')->assertJsonPath('data.timezone', 'America/Sao_Paulo');

        $this->getJson($this->companyUrl('/business-hours'))
            ->assertJsonCount(2, 'data.periods')
            ->assertJsonPath('data.periods.0', ['weekday' => 6, 'start_time' => '08:00', 'end_time' => '12:00']);
    }

    public function test_weekly_hours_validation(): void
    {
        $this->actAs(CompanyRole::Owner);

        $this->putJson($this->companyUrl('/business-hours'), ['periods' => [
            ['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00'],
            ['weekday' => 1, 'start_time' => '11:00', 'end_time' => '14:00'],
        ]])->assertJsonValidationErrors('periods');

        $this->putJson($this->companyUrl('/business-hours'), ['periods' => [
            ['weekday' => 7, 'start_time' => '18:00', 'end_time' => '09:00'],
        ]])->assertJsonValidationErrors(['periods.0.weekday', 'periods.0.end_time']);
    }

    public function test_professional_working_hours_and_company_fallback_flag(): void
    {
        $this->actAs(CompanyRole::Owner);
        $url = $this->companyUrl("/professionals/{$this->professional->id}/working-hours");

        $this->getJson($url)->assertJsonPath('data.uses_company_hours', true);

        $this->putJson($url, ['periods' => [['weekday' => 1, 'start_time' => '14:00', 'end_time' => '18:00']]])
            ->assertOk()
            ->assertJsonPath('data.uses_company_hours', false);

        $this->assertDatabaseCount('working_hours', 11); // 10 company rows + 1
    }

    public function test_receptionist_can_read_but_not_change_availability(): void
    {
        $this->actAs(CompanyRole::Receptionist);

        $this->getJson($this->companyUrl('/business-hours'))->assertOk();
        $this->putJson($this->companyUrl('/business-hours'), ['periods' => []])->assertForbidden();
        $this->postJson($this->companyUrl('/blocked-periods'), [
            'starts_at' => self::MONDAY.'T10:00', 'ends_at' => self::MONDAY.'T11:00',
        ])->assertForbidden();
    }

    public function test_exceptions_create_list_conflict_and_delete(): void
    {
        $this->actAs(CompanyRole::Owner);

        $id = $this->postJson($this->companyUrl('/availability-exceptions'), [
            'date' => '2026-12-25', 'is_closed' => true, 'reason' => 'Natal',
        ])->assertCreated()->assertJsonPath('data.start_time', null)->json('data.id');

        $this->postJson($this->companyUrl('/availability-exceptions'), [
            'date' => '2026-12-25', 'is_closed' => false, 'start_time' => '09:00', 'end_time' => '12:00',
        ])->assertJsonValidationErrors('date');

        // The same date for a professional is a different scope.
        $this->postJson($this->companyUrl('/availability-exceptions'), [
            'professional_id' => $this->professional->id,
            'date' => '2026-12-24', 'is_closed' => false, 'start_time' => '09:00', 'end_time' => '12:00',
        ])->assertCreated();

        $this->getJson($this->companyUrl('/availability-exceptions?from=2026-12-01&to=2026-12-31'))->assertJsonCount(2, 'data');

        $this->deleteJson($this->companyUrl("/availability-exceptions/{$id}"))->assertNoContent();
        $this->assertDatabaseMissing('availability_exceptions', ['id' => $id]);
    }

    public function test_exception_with_hours_requires_a_valid_range(): void
    {
        $this->actAs(CompanyRole::Owner);

        $this->postJson($this->companyUrl('/availability-exceptions'), [
            'date' => '2026-12-24', 'is_closed' => false, 'start_time' => '12:00', 'end_time' => '09:00',
        ])->assertJsonValidationErrors('end_time');
    }

    public function test_blocked_periods_store_utc_and_list_by_local_range(): void
    {
        $this->actAs(CompanyRole::Admin);

        $this->postJson($this->companyUrl('/blocked-periods'), [
            'professional_id' => $this->professional->id,
            'starts_at' => self::MONDAY.'T10:00',
            'ends_at' => self::MONDAY.'T11:30',
            'reason' => 'Treinamento',
        ])->assertCreated()
            ->assertJsonPath('data.starts_at', '2026-11-16T13:00:00Z')
            ->assertJsonPath('data.local.ends_at', '2026-11-16 11:30');

        $this->postJson($this->companyUrl('/blocked-periods'), [
            'starts_at' => self::MONDAY.'T11:00', 'ends_at' => self::MONDAY.'T10:00',
        ])->assertJsonValidationErrors('ends_at');

        $this->getJson($this->companyUrl('/blocked-periods?from='.self::MONDAY.'&to='.self::MONDAY))->assertJsonCount(1, 'data');
        $this->getJson($this->companyUrl('/blocked-periods?from=2026-11-17&to=2026-11-17'))->assertJsonCount(0, 'data');
    }

    public function test_availability_records_of_another_company_are_isolated(): void
    {
        $this->actAs(CompanyRole::Owner);
        $foreignProfessional = Professional::factory()->for($this->otherCompany)->create();
        $foreignBlock = BlockedPeriod::create([
            'company_id' => $this->otherCompany->id,
            'starts_at' => now(), 'ends_at' => now()->addHour(),
        ]);
        $foreignException = AvailabilityException::create([
            'company_id' => $this->otherCompany->id, 'date' => '2026-12-25', 'is_closed' => true,
        ]);

        $this->deleteJson($this->companyUrl("/blocked-periods/{$foreignBlock->id}"))->assertNotFound();
        $this->deleteJson($this->companyUrl("/availability-exceptions/{$foreignException->id}"))->assertNotFound();
        $this->getJson($this->companyUrl("/professionals/{$foreignProfessional->id}/working-hours"))->assertNotFound();
        $this->postJson($this->companyUrl('/blocked-periods'), [
            'professional_id' => $foreignProfessional->id,
            'starts_at' => self::MONDAY.'T10:00', 'ends_at' => self::MONDAY.'T11:00',
        ])->assertJsonValidationErrors('professional_id');

        $this->assertModelExists($foreignBlock);
        $this->assertModelExists($foreignException);
    }
}
