<?php

namespace Tests\Concerns;

use App\Modules\Availability\Models\WorkingHour;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Models\Customer;
use App\Modules\Professionals\Models\Professional;
use App\Modules\Services\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;

/**
 * Reference scenario (company timezone America/Sao_Paulo, UTC-3, no DST):
 *   "now"           Friday 2026-11-13 09:00 local (12:00 UTC)
 *   business hours  Mon-Fri 09:00-12:00 and 13:00-18:00 (lunch break 12:00-13:00)
 *   service         60 min + 15 min buffer, R$ 120,00
 *   booking day     Monday 2026-11-16
 */
trait BuildsSchedule
{
    use ActsAsCompanyMember;

    protected Professional $professional;

    protected Service $service;

    protected Customer $customer;

    protected const MONDAY = '2026-11-16';

    protected function setUpSchedule(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-13 12:00:00', 'UTC'));

        $this->company = Company::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $this->otherCompany = Company::factory()->create();

        foreach (range(1, 5) as $weekday) {
            $this->weekly(null, $weekday, '09:00', '12:00');
            $this->weekly(null, $weekday, '13:00', '18:00');
        }

        $this->service = Service::factory()->for($this->company)->create([
            'name' => 'Corte',
            'price_cents' => 12000,
            'duration_minutes' => 60,
            'buffer_minutes' => 15,
        ]);
        $this->professional = $this->makeProfessional();
        $this->customer = Customer::factory()->for($this->company)->create();
    }

    protected function makeProfessional(array $attributes = []): Professional
    {
        $professional = Professional::factory()->for($this->company)->create($attributes);
        $professional->syncServices([$this->service->id]);

        return $professional;
    }

    protected function weekly(?string $professionalId, int $weekday, string $start, string $end): void
    {
        WorkingHour::create([
            'company_id' => $this->company->id,
            'professional_id' => $professionalId,
            'weekday' => $weekday,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }

    /** @return array<string, mixed> */
    protected function bookingPayload(string $localStart, array $overrides = []): array
    {
        return [
            'customer_id' => $this->customer->id,
            'professional_id' => $this->professional->id,
            'service_id' => $this->service->id,
            'starts_at' => $localStart,
            ...$overrides,
        ];
    }

    protected function book(string $localStart, array $overrides = []): TestResponse
    {
        return $this->postJson($this->companyUrl('/appointments'), $this->bookingPayload($localStart, $overrides));
    }

    protected function bookOk(string $localStart, array $overrides = []): string
    {
        return $this->book($localStart, $overrides)->assertCreated()->json('data.id');
    }
}
