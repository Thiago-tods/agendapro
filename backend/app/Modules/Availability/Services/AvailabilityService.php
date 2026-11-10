<?php

namespace App\Modules\Availability\Services;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Availability\Models\AvailabilityException;
use App\Modules\Availability\Models\BlockedPeriod;
use App\Modules\Availability\Models\WorkingHour;
use App\Modules\Companies\Models\Company;
use App\Modules\Professionals\Models\Professional;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Decides whether a professional can take an appointment in a given interval.
 *
 * Local schedules (weekly hours, date exceptions) are evaluated in the company's timezone,
 * as minutes since local midnight. Absolute data (blocked periods, other appointments) is
 * compared in UTC. Every interval is half-open: [start, end).
 *
 * Resolution order for one local date, at each level (company, professional):
 *   1. date exceptions for that level, if any (a closed one wins; otherwise their ranges)
 *   2. weekly hours for that weekday
 * A professional without any weekly hours configured follows the company hours.
 * The bookable time is the intersection of the company and professional schedules.
 */
class AvailabilityService
{
    /**
     * @throws ValidationException when outside working hours or inside a blocked period (422)
     * @throws ConflictHttpException when another appointment occupies the interval (409)
     */
    public function assertBookable(
        Company $company,
        Professional $professional,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        CarbonImmutable $occupiedUntil,
        ?string $ignoreAppointmentId = null,
    ): void {
        $this->assertWithinSchedule($company, $professional, $startsAt, $endsAt);
        $this->assertNotBlocked($company, $professional, $startsAt, $occupiedUntil);
        $this->assertNoOverlap($professional, $startsAt, $occupiedUntil, $ignoreAppointmentId);
    }

    /**
     * Open ranges for a professional on a local date, in minutes since local midnight.
     *
     * @return list<array{int, int}>
     */
    public function openRanges(Company $company, Professional $professional, CarbonImmutable $localDate): array
    {
        $companyRanges = $this->rangesFor($company, null, $localDate) ?? [];
        $professionalRanges = $this->rangesFor($company, $professional->id, $localDate) ?? $companyRanges;

        return $this->intersect($companyRanges, $professionalRanges);
    }

    /**
     * Company-level open ranges on a local date, in minutes since local midnight.
     *
     * @return list<array{int, int}>
     */
    public function companyRanges(Company $company, CarbonImmutable $localDate): array
    {
        return $this->rangesFor($company, null, $localDate) ?? [];
    }

    private function assertWithinSchedule(Company $company, Professional $professional, CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        $localStart = $startsAt->setTimezone($company->timezone);
        $localEnd = $endsAt->setTimezone($company->timezone);
        $day = $localStart->startOfDay();

        // The service must start and end on the same local day ([start, end) may end exactly at midnight).
        if (! $localEnd->subSecond()->isSameDay($localStart)) {
            $this->fail('O atendimento deve começar e terminar no mesmo dia.');
        }

        $startMinute = (int) $day->diffInMinutes($localStart);
        $endMinute = (int) $day->diffInMinutes($localEnd);

        foreach ($this->openRanges($company, $professional, $day) as [$open, $close]) {
            if ($startMinute >= $open && $endMinute <= $close) {
                return;
            }
        }

        $this->fail('O horário está fora do expediente da empresa ou do profissional.');
    }

    private function assertNotBlocked(Company $company, Professional $professional, CarbonImmutable $from, CarbonImmutable $until): void
    {
        $blocked = BlockedPeriod::query()
            ->where('company_id', $company->id)
            ->where(fn ($q) => $q->whereNull('professional_id')->orWhere('professional_id', $professional->id))
            ->where('starts_at', '<', $until->utc())
            ->where('ends_at', '>', $from->utc())
            ->exists();

        if ($blocked) {
            $this->fail('O horário coincide com um período bloqueado.');
        }
    }

    private function assertNoOverlap(Professional $professional, CarbonImmutable $from, CarbonImmutable $until, ?string $ignoreId): void
    {
        $conflict = Appointment::query()
            ->where('professional_id', $professional->id)
            ->occupyingBetween($from, $until)
            ->when($ignoreId, fn ($q, string $id) => $q->whereKeyNot($id))
            ->exists();

        if ($conflict) {
            throw new ConflictHttpException('O profissional já possui um agendamento neste horário.');
        }
    }

    /**
     * Ranges for one level on one local date, or null when that level has no configuration at all
     * (only meaningful for professionals, who then follow the company hours).
     *
     * @return list<array{int, int}>|null
     */
    private function rangesFor(Company $company, ?string $professionalId, CarbonImmutable $localDate): ?array
    {
        $exceptions = AvailabilityException::query()
            ->where('company_id', $company->id)
            ->where('professional_id', $professionalId)
            ->whereDate('date', $localDate->toDateString())
            ->get();

        if ($exceptions->isNotEmpty()) {
            if ($exceptions->contains('is_closed', true)) {
                return [];
            }

            return $this->normalize($exceptions->map(fn (AvailabilityException $e): array => [
                $this->toMinutes($e->start_time), $this->toMinutes($e->end_time),
            ])->all());
        }

        $weekly = WorkingHour::query()
            ->where('company_id', $company->id)
            ->where('professional_id', $professionalId);

        if ($professionalId !== null && ! (clone $weekly)->exists()) {
            return null;
        }

        return $this->normalize($weekly->where('weekday', $localDate->dayOfWeek)->get()
            ->map(fn (WorkingHour $h): array => [$this->toMinutes($h->start_time), $this->toMinutes($h->end_time)])
            ->all());
    }

    /**
     * @param  list<array{int, int}>  $a
     * @param  list<array{int, int}>  $b
     * @return list<array{int, int}>
     */
    private function intersect(array $a, array $b): array
    {
        $result = [];

        foreach ($a as [$aStart, $aEnd]) {
            foreach ($b as [$bStart, $bEnd]) {
                $start = max($aStart, $bStart);
                $end = min($aEnd, $bEnd);

                if ($start < $end) {
                    $result[] = [$start, $end];
                }
            }
        }

        return $this->normalize($result);
    }

    /**
     * Sort and merge touching/overlapping ranges, so a booking spanning 09:00-12:00 + 12:00-14:00 fits.
     *
     * @param  list<array{int, int}>  $ranges
     * @return list<array{int, int}>
     */
    private function normalize(array $ranges): array
    {
        usort($ranges, fn (array $x, array $y): int => $x[0] <=> $y[0]);

        $merged = [];
        foreach ($ranges as [$start, $end]) {
            $last = array_key_last($merged);

            if ($last !== null && $start <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }

    private function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['starts_at' => $message]);
    }
}
