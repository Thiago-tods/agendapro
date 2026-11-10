<?php

namespace App\Modules\Availability\Http\Controllers;

use App\Modules\Availability\Http\Requests\SaveWeeklyHoursRequest;
use App\Modules\Availability\Http\Requests\StoreAvailabilityExceptionRequest;
use App\Modules\Availability\Http\Requests\StoreBlockedPeriodRequest;
use App\Modules\Availability\Models\AvailabilityException;
use App\Modules\Availability\Models\BlockedPeriod;
use App\Modules\Availability\Models\WorkingHour;
use App\Modules\Companies\Models\Company;
use App\Modules\Professionals\Models\Professional;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Availability configuration. Every member may read it (the agenda needs it);
 * only roles with canManageAvailability may change it.
 * Changing availability never alters existing appointments.
 */
class AvailabilityController
{
    // --- Weekly hours ------------------------------------------------------------------

    public function businessHours(Company $company): JsonResponse
    {
        Gate::authorize('view', $company);

        return $this->weeklyResponse($company, null);
    }

    public function updateBusinessHours(SaveWeeklyHoursRequest $request, Company $company): JsonResponse
    {
        $this->replaceWeekly($company, null, $request->validated('periods'));

        return $this->weeklyResponse($company, null);
    }

    public function workingHours(Company $company, Professional $professional): JsonResponse
    {
        Gate::authorize('view', $company);

        return $this->weeklyResponse($company, $professional->id);
    }

    public function updateWorkingHours(SaveWeeklyHoursRequest $request, Company $company, Professional $professional): JsonResponse
    {
        $this->replaceWeekly($company, $professional->id, $request->validated('periods'));

        return $this->weeklyResponse($company, $professional->id);
    }

    // --- Date exceptions ---------------------------------------------------------------

    public function exceptions(Request $request, Company $company): JsonResponse
    {
        Gate::authorize('view', $company);

        $filters = $this->validateRange($request);

        $rows = $company->availabilityExceptions()
            ->whereBetween('date', [$filters['from'], $filters['to']])
            ->when(array_key_exists('professional_id', $filters), fn ($q) => $q->where('professional_id', $filters['professional_id']))
            ->orderBy('date')->orderBy('start_time')
            ->get();

        return response()->json(['data' => $rows->map($this->exceptionData(...))->values()]);
    }

    public function storeException(StoreAvailabilityExceptionRequest $request, Company $company): JsonResponse
    {
        $data = $request->validated();

        $exception = $company->availabilityExceptions()->create([
            'professional_id' => $data['professional_id'] ?? null,
            'date' => $data['date'],
            'is_closed' => $data['is_closed'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'reason' => $data['reason'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->exceptionData($exception->refresh())], 201);
    }

    public function destroyException(Company $company, AvailabilityException $availabilityException): Response
    {
        Gate::authorize('manageAvailability', $company);

        $availabilityException->delete();

        return response()->noContent();
    }

    // --- Blocked periods ---------------------------------------------------------------

    public function blockedPeriods(Request $request, Company $company): JsonResponse
    {
        Gate::authorize('view', $company);

        $filters = $this->validateRange($request);
        $from = CarbonImmutable::parse($filters['from'], $company->timezone)->startOfDay()->utc();
        $until = CarbonImmutable::parse($filters['to'], $company->timezone)->addDay()->startOfDay()->utc();

        $rows = $company->blockedPeriods()
            ->where('starts_at', '<', $until)
            ->where('ends_at', '>', $from)
            ->when(array_key_exists('professional_id', $filters), fn ($q) => $q->where('professional_id', $filters['professional_id']))
            ->orderBy('starts_at')
            ->get();

        return response()->json(['data' => $rows->map(fn (BlockedPeriod $b): array => $this->blockedData($b, $company))->values()]);
    }

    public function storeBlockedPeriod(StoreBlockedPeriodRequest $request, Company $company): JsonResponse
    {
        $block = $company->blockedPeriods()->create([
            'professional_id' => $request->validated('professional_id'),
            'starts_at' => $request->startsAt(),
            'ends_at' => $request->endsAt(),
            'reason' => $request->validated('reason'),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->blockedData($block, $company)], 201);
    }

    public function destroyBlockedPeriod(Company $company, BlockedPeriod $blockedPeriod): Response
    {
        Gate::authorize('manageAvailability', $company);

        $blockedPeriod->delete();

        return response()->noContent();
    }

    // --- Helpers -----------------------------------------------------------------------

    /** @param  list<array{weekday: int, start_time: string, end_time: string}>  $periods */
    private function replaceWeekly(Company $company, ?string $professionalId, array $periods): void
    {
        DB::transaction(function () use ($company, $professionalId, $periods): void {
            $company->workingHours()->where('professional_id', $professionalId)->delete();

            foreach ($periods as $period) {
                $company->workingHours()->create([
                    'professional_id' => $professionalId,
                    'weekday' => $period['weekday'],
                    'start_time' => $period['start_time'],
                    'end_time' => $period['end_time'],
                ]);
            }
        });
    }

    private function weeklyResponse(Company $company, ?string $professionalId): JsonResponse
    {
        /** @var Collection<int, WorkingHour> $rows */
        $rows = $company->workingHours()
            ->where('professional_id', $professionalId)
            ->orderBy('weekday')->orderBy('start_time')
            ->get();

        return response()->json([
            'data' => [
                'timezone' => $company->timezone,
                // For a professional, an empty list means the company hours apply.
                'uses_company_hours' => $professionalId !== null && $rows->isEmpty(),
                'periods' => $rows->map(fn (WorkingHour $h): array => [
                    'weekday' => $h->weekday,
                    'start_time' => substr($h->start_time, 0, 5),
                    'end_time' => substr($h->end_time, 0, 5),
                ])->values(),
            ],
        ]);
    }

    /** @return array{from: string, to: string, professional_id?: string|null} */
    private function validateRange(Request $request): array
    {
        return $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'professional_id' => ['sometimes', 'nullable', 'uuid'],
        ]);
    }

    /** @return array<string, mixed> */
    private function exceptionData(AvailabilityException $e): array
    {
        return [
            'id' => $e->id,
            'professional_id' => $e->professional_id,
            'date' => $e->date->toDateString(),
            'is_closed' => $e->is_closed,
            'start_time' => $e->start_time ? substr($e->start_time, 0, 5) : null,
            'end_time' => $e->end_time ? substr($e->end_time, 0, 5) : null,
            'reason' => $e->reason,
        ];
    }

    /** @return array<string, mixed> */
    private function blockedData(BlockedPeriod $b, Company $company): array
    {
        return [
            'id' => $b->id,
            'professional_id' => $b->professional_id,
            'starts_at' => $b->starts_at->toIso8601ZuluString(),
            'ends_at' => $b->ends_at->toIso8601ZuluString(),
            'local' => [
                'timezone' => $company->timezone,
                'starts_at' => $b->starts_at->setTimezone($company->timezone)->format('Y-m-d H:i'),
                'ends_at' => $b->ends_at->setTimezone($company->timezone)->format('Y-m-d H:i'),
            ],
            'reason' => $b->reason,
        ];
    }
}
