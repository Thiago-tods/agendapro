<?php

namespace App\Modules\Appointments\Enums;

enum AppointmentEventType: string
{
    case Created = 'created';
    case Confirmed = 'confirmed';
    case Rescheduled = 'rescheduled';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Agendamento criado',
            self::Confirmed => 'Agendamento confirmado',
            self::Rescheduled => 'Agendamento remarcado',
            self::CheckedIn => 'Check-in realizado',
            self::Completed => 'Atendimento concluído',
            self::Cancelled => 'Agendamento cancelado',
            self::NoShow => 'Cliente não compareceu',
        };
    }

    public static function forStatus(AppointmentStatus $status): self
    {
        return match ($status) {
            AppointmentStatus::Confirmed => self::Confirmed,
            AppointmentStatus::CheckedIn => self::CheckedIn,
            AppointmentStatus::Completed => self::Completed,
            AppointmentStatus::Cancelled => self::Cancelled,
            AppointmentStatus::NoShow => self::NoShow,
            AppointmentStatus::Scheduled => self::Created,
        };
    }
}
