<?php

namespace App\Modules\Availability\Http\Controllers;

use App\Modules\Availability\Models\BlockedPeriod;
use App\Modules\Availability\Services\AvailabilityService;
use App\Modules\Companies\Models\Company;
use App\Modules\Professionals\Models\Professional;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Open hours and blocked periods for the visual calendar, already resolved by the same
 * AvailabilityService that validates bookings, so the SPA never re-implements the rules.
 * Ranges are minutes since local midnight in the company's timezone.
 */
class CalendarAvailabilityController
{
    private const MAX_DAYS = 42;

    public function __invoke(Request $request, Company $company, AvailabilityService $availability): JsonResponse
    {
        Gate::authorize('view', $company);

        $filters = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'professional_ids' => ['nullable', 'array', 'max:50'],
            'professional_ids.*' => ['uuid'],
        ]);

        $tz = $company->timezone;
        $from = CarbonImmutable::parse($filters['from'], $tz)->startOfDay();
        $to = CarbonImmutable::parse($filters['to'], $tz)->startOfDay();

        abort_if($from->diffInDays($to) + 1 > self::MAX_DAYS, 422, 'O período consultado deve ter no máximo 42 dias.');

        $professionals = $company->professionals()
            ->where('is_active', true)
            ->when($filters['professional_ids'] ?? null, fn ($q, array $ids) => $q->whereIn('id', $ids))
            ->orderBy('name')
            ->get();

        $days = [];
        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $days[] = [
                'date' => $day->toDateString(),
                'company' => $availability->companyRanges($company, $day),
                'professionals' => $professionals->mapWithKeys(fn (Professional $p): array => [
                    $p->id => $availability->openRanges($company, $p, $day),
                ])->all() ?: (object) [],
            ];
        }

        $blocked = $company->blockedPeriods()
            ->where('starts_at', '<', $to->addDay()->utc())
            ->where('ends_at', '>', $from->utc())
            ->when(
                $filters['professional_ids'] ?? null,
                fn ($q, array $ids) => $q->where(fn ($w) => $w->whereNull('professional_id')->orWhereIn('professional_id', $ids)),
            )
            ->orderBy('starts_at')
            ->get()
            ->map(fn (BlockedPeriod $b): array => [
                'id' => $b->id,
                'professional_id' => $b->professional_id,
                'reason' => $b->reason,
                'starts_at' => $b->starts_at->toIso8601ZuluString(),
                'ends_at' => $b->ends_at->toIso8601ZuluString(),
                'local_start' => $b->starts_at->setTimezone($tz)->format('Y-m-d H:i'),
                'local_end' => $b->ends_at->setTimezone($tz)->format('Y-m-d H:i'),
            ]);

        return response()->json(['data' => ['timezone' => $tz, 'days' => $days, 'blocked' => $blocked]]);
    }
}
