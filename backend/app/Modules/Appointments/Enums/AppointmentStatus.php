<?php

namespace App\Modules\Appointments\Enums;

/**
 * Appointment lifecycle.
 *
 *   scheduled ──► confirmed ──► checked_in ──► completed
 *       │  │          │  │
 *       │  └──────────┼──┴──► cancelled
 *       └─────────────┴─────► no_show
 *   scheduled ──────────────► checked_in   (walk-in without confirmation)
 */
enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendado',
            self::Confirmed => 'Confirmado',
            self::CheckedIn => 'Em atendimento',
            self::Completed => 'Concluído',
            self::Cancelled => 'Cancelado',
            self::NoShow => 'Não compareceu',
        };
    }

    /**
     * Statuses that occupy the professional's time. Must match the WHERE clause of the
     * appointments_no_overlap exclusion constraint.
     *
     * @return list<self>
     */
    public static function occupying(): array
    {
        return [self::Scheduled, self::Confirmed, self::CheckedIn, self::Completed];
    }

    /** @return list<string> */
    public static function occupyingValues(): array
    {
        return array_map(fn (self $s): string => $s->value, self::occupying());
    }

    /** Can still be rescheduled (time or professional changed). */
    public function isReschedulable(): bool
    {
        return in_array($this, [self::Scheduled, self::Confirmed], true);
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, match ($this) {
            self::Scheduled => [self::Confirmed, self::CheckedIn, self::Cancelled, self::NoShow],
            self::Confirmed => [self::CheckedIn, self::Cancelled, self::NoShow],
            self::CheckedIn => [self::Completed],
            self::Completed, self::Cancelled, self::NoShow => [],
        }, true);
    }
}
