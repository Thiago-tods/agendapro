<?php

namespace Tests\Feature\Availability;

use App\Modules\Availability\Models\AvailabilityException;
use App\Modules\Availability\Models\BlockedPeriod;
use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Professionals\Models\Professional;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsSchedule;
use Tests\TestCase;

class CalendarAvailabilityTest extends TestCase
{
    use BuildsSchedule, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSchedule();
        $this->actAs(CompanyRole::Professional); // every member may read availability
    }

    private function url(string $query): string
    {
        return $this->companyUrl('/calendar/availability?'.$query);
    }

    public function test_returns_resolved_ranges_per_day_for_company_and_professionals(): void
    {
        $this->weekly($this->professional->id, 1, '14:00', '20:00'); // narrower than the company on Mondays

        $response = $this->getJson($this->url('from=2026-11-15&to=2026-11-16'))->assertOk();

        $response->assertJsonPath('data.timezone', 'America/Sao_Paulo')
            ->assertJsonPath('data.days.0.date', '2026-11-15')
            ->assertJsonPath('data.days.0.company', []) // Sunday: closed
            ->assertJsonPath('data.days.1.company', [[540, 720], [780, 1080]])
            // Intersection with the company hours: 14:00-18:00.
            ->assertJsonPath("data.days.1.professionals.{$this->professional->id}", [[840, 1080]]);
    }

    public function test_exceptions_and_blocks_are_reflected(): void
    {
        AvailabilityException::create([
            'company_id' => $this->company->id, 'date' => self::MONDAY, 'is_closed' => true, 'reason' => 'Feriado',
        ]);
        BlockedPeriod::create([
            'company_id' => $this->company->id, 'professional_id' => $this->professional->id,
            'starts_at' => CarbonImmutable::parse('2026-11-17 10:00', 'America/Sao_Paulo')->utc(),
            'ends_at' => CarbonImmutable::parse('2026-11-17 11:30', 'America/Sao_Paulo')->utc(),
            'reason' => 'Curso',
        ]);

        $this->getJson($this->url('from=2026-11-16&to=2026-11-17'))
            ->assertJsonPath('data.days.0.company', [])
            ->assertJsonPath("data.days.0.professionals.{$this->professional->id}", [])
            ->assertJsonPath('data.blocked.0.local_start', '2026-11-17 10:00')
            ->assertJsonPath('data.blocked.0.local_end', '2026-11-17 11:30')
            ->assertJsonPath('data.blocked.0.reason', 'Curso');
    }

    public function test_filters_professionals_and_excludes_inactive_ones(): void
    {
        $other = $this->makeProfessional();
        $inactive = $this->makeProfessional(['is_active' => false]);

        $all = $this->getJson($this->url('from=2026-11-16&to=2026-11-16'))->json('data.days.0.professionals');
        $this->assertEqualsCanonicalizing([$this->professional->id, $other->id], array_keys($all));
        $this->assertArrayNotHasKey($inactive->id, $all);

        $one = $this->getJson($this->url("from=2026-11-16&to=2026-11-16&professional_ids[]={$other->id}"))->json('data.days.0.professionals');
        $this->assertSame([$other->id], array_keys($one));
    }

    public function test_validates_range_and_isolates_companies(): void
    {
        $this->getJson($this->url('from=2026-11-01&to=2026-12-31'))->assertUnprocessable();
        $this->getJson($this->url('from=2026-11-16'))->assertJsonValidationErrors('to');

        $foreign = Professional::factory()->for($this->otherCompany)->create();
        $this->getJson($this->url("from=2026-11-16&to=2026-11-16&professional_ids[]={$foreign->id}"))
            ->assertOk()
            ->assertJsonPath('data.days.0.professionals', []);

        $this->getJson($this->companyUrl('/calendar/availability?from=2026-11-16&to=2026-11-16', $this->otherCompany))->assertNotFound();
    }
}
