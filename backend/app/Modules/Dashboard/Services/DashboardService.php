<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Appointments\Enums\AppointmentStatus;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Models\AppointmentEvent;
use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Companies\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read-only aggregates for the dashboard, computed from appointments.
 *
 * Day and week boundaries are taken in the company's timezone. Revenue is the sum of the
 * price snapshot of completed appointments, in cents, and is only returned to roles allowed
 * to see financial data (null otherwise).
 *
 * Periods: "today" is the local calendar day; the comparison period is the last 30 local
 * days including today, compared with the 30 days before it.
 */
class DashboardService
{
    private const PERIOD_DAYS = 30;

    private const TREND_WEEKS = 8;

    private const LIST_LIMIT = 6;

    private const ACTIVITY_LIMIT = 8;

    private const RANKING_LIMIT = 5;

    /** @return array<string, mixed> */
    public function build(Company $company, CompanyRole $role, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $tz = $company->timezone;
        $financial = $role->canViewFinancials();

        $todayStart = $now->setTimezone($tz)->startOfDay();
        $tomorrowStart = $todayStart->addDay();
        $periodStart = $todayStart->subDays(self::PERIOD_DAYS - 1);
        $previousStart = $periodStart->subDays(self::PERIOD_DAYS);

        return [
            'timezone' => $tz,
            'generated_at' => $now->toIso8601ZuluString(),
            'today' => $this->today($company, $now, $todayStart, $tomorrowStart),
            'period' => [
                'days' => self::PERIOD_DAYS,
                'from' => $periodStart->toDateString(),
                'to' => $todayStart->toDateString(),
                'current' => $this->periodMetrics($company, $periodStart, $tomorrowStart, $financial),
                'previous' => $this->periodMetrics($company, $previousStart, $periodStart, $financial),
            ],
            'weekly_trend' => $this->weeklyTrend($company, $todayStart, $tomorrowStart, $financial),
            'upcoming' => $this->upcoming($company, $now),
            'recent_activity' => $this->recentActivity($company),
            'top_services' => $this->topServices($company, $periodStart, $tomorrowStart, $financial),
            'top_professionals' => $this->topProfessionals($company, $periodStart, $tomorrowStart),
        ];
    }

    /** @return Builder<Appointment> */
    private function appointments(Company $company): Builder
    {
        return Appointment::query()->where('appointments.company_id', $company->id);
    }

    /** @return array<string, int> */
    private function today(Company $company, CarbonImmutable $now, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $byStatus = $this->appointments($company)
            ->startingBetween($from, $until)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (AppointmentStatus ...$statuses): int => (int) collect($statuses)->sum(fn ($s) => $byStatus[$s->value] ?? 0);

        return [
            // Everything booked for today that was not cancelled.
            'total' => $count(AppointmentStatus::Scheduled, AppointmentStatus::Confirmed, AppointmentStatus::CheckedIn, AppointmentStatus::Completed, AppointmentStatus::NoShow),
            'remaining' => $this->appointments($company)
                ->startingBetween($from, $until)
                ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Confirmed->value])
                ->where('starts_at', '>=', $now)
                ->count(),
            'in_progress' => $count(AppointmentStatus::CheckedIn),
            'completed' => $count(AppointmentStatus::Completed),
            'no_show' => $count(AppointmentStatus::NoShow),
            'cancelled' => $count(AppointmentStatus::Cancelled),
            // Future bookings (any day) still waiting for the customer's confirmation.
            'awaiting_confirmation' => $this->appointments($company)
                ->where('status', AppointmentStatus::Scheduled->value)
                ->where('starts_at', '>=', $now)
                ->count(),
        ];
    }

    /** @return array<string, int|float|null> */
    private function periodMetrics(Company $company, CarbonImmutable $from, CarbonImmutable $until, bool $financial): array
    {
        $row = $this->appointments($company)
            ->startingBetween($from, $until)
            ->selectRaw("count(*) filter (where status <> 'cancelled') as booked")
            ->selectRaw("count(*) filter (where status = 'completed') as completed")
            ->selectRaw("count(*) filter (where status = 'no_show') as no_show")
            ->selectRaw("count(*) filter (where status = 'cancelled') as cancelled")
            ->selectRaw("coalesce(sum(service_price_cents) filter (where status = 'completed'), 0) as revenue")
            ->toBase()
            ->first();

        $completed = (int) $row->completed;
        $noShow = (int) $row->no_show;

        return [
            'booked' => (int) $row->booked,
            'completed' => $completed,
            'no_show' => $noShow,
            'cancelled' => (int) $row->cancelled,
            // Completed / (completed + no-show): only appointments whose outcome is known.
            'attendance_rate' => $completed + $noShow > 0 ? round($completed / ($completed + $noShow), 4) : null,
            'revenue_cents' => $financial ? (int) $row->revenue : null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function weeklyTrend(Company $company, CarbonImmutable $todayStart, CarbonImmutable $until, bool $financial): array
    {
        $firstWeek = $todayStart->startOfWeek(CarbonImmutable::MONDAY)->subWeeks(self::TREND_WEEKS - 1);
        $tz = $company->timezone;

        $rows = $this->appointments($company)
            ->startingBetween($firstWeek, $until)
            ->selectRaw("to_char(date_trunc('week', starts_at at time zone ?), 'YYYY-MM-DD') as week", [$tz])
            ->selectRaw("count(*) filter (where status <> 'cancelled') as booked")
            ->selectRaw("count(*) filter (where status = 'completed') as completed")
            ->selectRaw("coalesce(sum(service_price_cents) filter (where status = 'completed'), 0) as revenue")
            ->groupBy('week')
            ->toBase()
            ->get()
            ->keyBy('week');

        $weeks = [];
        for ($i = 0; $i < self::TREND_WEEKS; $i++) {
            $week = $firstWeek->addWeeks($i)->toDateString();
            $row = $rows->get($week);

            $weeks[] = [
                'week_start' => $week,
                'booked' => (int) ($row->booked ?? 0),
                'completed' => (int) ($row->completed ?? 0),
                'revenue_cents' => $financial ? (int) ($row->revenue ?? 0) : null,
            ];
        }

        return $weeks;
    }

    /** @return Collection<int, Appointment> */
    private function upcoming(Company $company, CarbonImmutable $now): Collection
    {
        return $this->appointments($company)
            ->with(['customer:id,name,phone', 'professional:id,name,color'])
            ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Confirmed->value, AppointmentStatus::CheckedIn->value])
            // Still running (checked in, not over yet) or starting later.
            ->where('occupied_until', '>', $now)
            ->orderBy('starts_at')
            ->orderBy('id')
            ->limit(self::LIST_LIMIT)
            ->get();
    }

    /** @return Collection<int, AppointmentEvent> */
    private function recentActivity(Company $company): Collection
    {
        return AppointmentEvent::query()
            ->where('company_id', $company->id)
            ->with(['actor:id,name', 'appointment:id,customer_id,service_name,starts_at', 'appointment.customer:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::ACTIVITY_LIMIT)
            ->get();
    }

    /** @return list<array<string, mixed>> */
    private function topServices(Company $company, CarbonImmutable $from, CarbonImmutable $until, bool $financial): array
    {
        return $this->appointments($company)
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->startingBetween($from, $until)
            ->where('appointments.status', AppointmentStatus::Completed->value)
            ->groupBy('services.id', 'services.name', 'services.color')
            ->select('services.id', 'services.name', 'services.color')
            ->selectRaw('count(*) as completed')
            ->selectRaw('sum(appointments.service_price_cents) as revenue')
            ->orderByDesc('completed')
            ->orderBy('services.name')
            ->limit(self::RANKING_LIMIT)
            ->toBase()
            ->get()
            ->map(fn ($row): array => [
                'id' => $row->id,
                'name' => $row->name,
                'color' => $row->color,
                'completed' => (int) $row->completed,
                'revenue_cents' => $financial ? (int) $row->revenue : null,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function topProfessionals(Company $company, CarbonImmutable $from, CarbonImmutable $until): array
    {
        return $this->appointments($company)
            ->join('professionals', 'professionals.id', '=', 'appointments.professional_id')
            ->startingBetween($from, $until)
            ->whereIn('appointments.status', [AppointmentStatus::Completed->value, AppointmentStatus::NoShow->value])
            ->groupBy('professionals.id', 'professionals.name', 'professionals.color')
            ->select('professionals.id', 'professionals.name', 'professionals.color')
            ->selectRaw("count(*) filter (where appointments.status = 'completed') as completed")
            ->selectRaw("count(*) filter (where appointments.status = 'no_show') as no_show")
            ->orderByDesc('completed')
            ->orderBy('professionals.name')
            ->limit(self::RANKING_LIMIT)
            ->toBase()
            ->get()
            ->map(fn ($row): array => [
                'id' => $row->id,
                'name' => $row->name,
                'color' => $row->color,
                'completed' => (int) $row->completed,
                'no_show' => (int) $row->no_show,
                'attendance_rate' => round((int) $row->completed / max(1, (int) $row->completed + (int) $row->no_show), 4),
            ])
            ->all();
    }
}
