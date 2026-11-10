<?php

namespace App\Modules\Appointments\Services;

use App\Models\User;
use App\Modules\Appointments\Enums\AppointmentEventType;
use App\Modules\Appointments\Enums\AppointmentStatus;
use App\Modules\Appointments\Models\Appointment;
use App\Modules\Availability\Services\AvailabilityService;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Models\Customer;
use App\Modules\Professionals\Models\Professional;
use App\Modules\Services\Models\Service;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * The only place that writes appointments.
 *
 * Every operation runs in a transaction, locks the professional row (serializing bookings for
 * the same professional), re-validates all rules against current data and records an event.
 * The appointments_no_overlap exclusion constraint is the final guard: if a concurrent
 * transaction still slips through, PostgreSQL raises 23P01 and the caller gets 409.
 */
class AppointmentService
{
    /** Minutes of tolerance for booking/rescheduling to a start time that has just passed. */
    private const PAST_TOLERANCE_MINUTES = 5;

    /** Check-in opens this many minutes before the start time. */
    private const CHECK_IN_WINDOW_MINUTES = 120;

    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * @param  array{customer_id: string, professional_id: string, service_id: string, starts_at: string, notes?: string|null}  $data
     */
    public function create(Company $company, User $actor, array $data): Appointment
    {
        return $this->guardOverlap(fn (): Appointment => DB::transaction(function () use ($company, $actor, $data): Appointment {
            $professional = $this->lockProfessional($company, $data['professional_id']);
            $service = $company->services()->findOrFail($data['service_id']);
            $customer = $company->customers()->findOrFail($data['customer_id']);

            $this->assertBookingParties($professional, $service, $customer);

            $startsAt = $this->parseStart($company, $data['starts_at']);
            [$endsAt, $occupiedUntil] = $this->intervalFor($startsAt, $service->duration_minutes, $service->buffer_minutes);

            $this->availability->assertBookable($company, $professional, $startsAt, $endsAt, $occupiedUntil);

            $appointment = Appointment::create([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'professional_id' => $professional->id,
                'service_id' => $service->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'occupied_until' => $occupiedUntil,
                'status' => AppointmentStatus::Scheduled,
                'service_name' => $service->name,
                'service_price_cents' => $service->price_cents,
                'service_duration_minutes' => $service->duration_minutes,
                'service_buffer_minutes' => $service->buffer_minutes,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->record($appointment, $actor, AppointmentEventType::Created, null, AppointmentStatus::Scheduled, null, [
                'starts_at' => $startsAt->toIso8601ZuluString(),
                'professional_id' => $professional->id,
            ]);

            return $appointment;
        }));
    }

    /**
     * Moves the appointment to a new start (and optionally another professional).
     * Keeps the service snapshot; a confirmed appointment goes back to "scheduled".
     */
    public function reschedule(Appointment $appointment, User $actor, string $startsAtInput, ?string $professionalId, ?string $reason): Appointment
    {
        return $this->guardOverlap(fn (): Appointment => DB::transaction(function () use ($appointment, $actor, $startsAtInput, $professionalId, $reason): Appointment {
            $appointment = $this->lockAppointment($appointment);
            $company = $appointment->company;

            if (! $appointment->status->isReschedulable()) {
                $this->fail('status', "Um agendamento com status \"{$appointment->status->label()}\" não pode ser remarcado.");
            }

            $professional = $this->lockProfessional($company, $professionalId ?? $appointment->professional_id);

            if ($professional->id !== $appointment->professional_id) {
                $this->assertProfessionalCanPerform($professional, $appointment->service_id);
            } elseif (! $professional->is_active) {
                $this->fail('professional_id', 'O profissional está inativo.');
            }

            $startsAt = $this->parseStart($company, $startsAtInput);
            [$endsAt, $occupiedUntil] = $this->intervalFor($startsAt, $appointment->service_duration_minutes, $appointment->service_buffer_minutes);

            if ($startsAt->equalTo($appointment->starts_at) && $professional->id === $appointment->professional_id) {
                $this->fail('starts_at', 'Informe um novo horário ou profissional.');
            }

            $this->availability->assertBookable($company, $professional, $startsAt, $endsAt, $occupiedUntil, $appointment->id);

            $before = [
                'starts_at' => $appointment->starts_at->toIso8601ZuluString(),
                'professional_id' => $appointment->professional_id,
            ];
            $fromStatus = $appointment->status;

            $appointment->update([
                'professional_id' => $professional->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'occupied_until' => $occupiedUntil,
                'status' => AppointmentStatus::Scheduled,
            ]);

            $this->record($appointment, $actor, AppointmentEventType::Rescheduled, $fromStatus, AppointmentStatus::Scheduled, $reason, [
                'from' => $before,
                'to' => ['starts_at' => $startsAt->toIso8601ZuluString(), 'professional_id' => $professional->id],
            ]);

            return $appointment;
        }));
    }

    public function transition(Appointment $appointment, User $actor, AppointmentStatus $target, ?string $reason = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $actor, $target, $reason): Appointment {
            $appointment = $this->lockAppointment($appointment);
            $from = $appointment->status;

            if (! $from->canTransitionTo($target)) {
                $this->fail('status', "Não é possível passar de \"{$from->label()}\" para \"{$target->label()}\".");
            }

            $now = CarbonImmutable::now();
            $attributes = ['status' => $target];

            match ($target) {
                AppointmentStatus::CheckedIn => $this->assertCheckInWindow($appointment, $now),
                AppointmentStatus::NoShow => $now->lessThan($appointment->starts_at)
                    ? $this->fail('status', 'Só é possível registrar o não comparecimento após o horário de início.')
                    : null,
                default => null,
            };

            if ($target === AppointmentStatus::CheckedIn) {
                $attributes['checked_in_at'] = $now;
            } elseif ($target === AppointmentStatus::Completed) {
                $attributes['completed_at'] = $now;
            } elseif ($target === AppointmentStatus::Cancelled) {
                $attributes += ['cancelled_at' => $now, 'cancelled_by' => $actor->id, 'cancellation_reason' => $reason];
            }

            $appointment->update($attributes);

            $this->record($appointment, $actor, AppointmentEventType::forStatus($target), $from, $target, $reason);

            return $appointment;
        });
    }

    private function lockProfessional(Company $company, string $professionalId): Professional
    {
        /** @var Professional */
        return $company->professionals()->whereKey($professionalId)->lockForUpdate()->firstOrFail();
    }

    private function lockAppointment(Appointment $appointment): Appointment
    {
        /** @var Appointment */
        return Appointment::query()->with('company')->whereKey($appointment->id)->lockForUpdate()->firstOrFail();
    }

    private function assertBookingParties(Professional $professional, Service $service, Customer $customer): void
    {
        if ($customer->isArchived()) {
            $this->fail('customer_id', 'O cliente está arquivado.');
        }

        if (! $service->is_active) {
            $this->fail('service_id', 'O serviço está inativo.');
        }

        $this->assertProfessionalCanPerform($professional, $service->id);
    }

    private function assertProfessionalCanPerform(Professional $professional, string $serviceId): void
    {
        if (! $professional->is_active) {
            $this->fail('professional_id', 'O profissional está inativo.');
        }

        if (! $professional->services()->whereKey($serviceId)->exists()) {
            $this->fail('professional_id', 'O profissional não realiza este serviço.');
        }
    }

    /**
     * Accepts ISO 8601 with offset ("2026-11-12T13:00:00Z") or a local wall-clock time without
     * offset ("2026-11-12T10:00"), which is interpreted in the company's timezone. Returns UTC.
     */
    private function parseStart(Company $company, string $input): CarbonImmutable
    {
        $startsAt = CarbonImmutable::parse($input, $company->timezone)->utc();

        if ($startsAt->second !== 0 || $startsAt->minute % 5 !== 0) {
            $this->fail('starts_at', 'O horário de início deve ser em intervalos de 5 minutos.');
        }

        if ($startsAt->lessThan(CarbonImmutable::now()->subMinutes(self::PAST_TOLERANCE_MINUTES))) {
            $this->fail('starts_at', 'Não é possível agendar em um horário que já passou.');
        }

        return $startsAt;
    }

    /** @return array{CarbonImmutable, CarbonImmutable} [ends_at, occupied_until] */
    private function intervalFor(CarbonImmutable $startsAt, int $durationMinutes, int $bufferMinutes): array
    {
        $endsAt = $startsAt->addMinutes($durationMinutes);

        return [$endsAt, $endsAt->addMinutes($bufferMinutes)];
    }

    private function assertCheckInWindow(Appointment $appointment, CarbonImmutable $now): void
    {
        if ($now->lessThan($appointment->starts_at->subMinutes(self::CHECK_IN_WINDOW_MINUTES))) {
            $this->fail('status', 'O check-in só pode ser feito a partir de 2 horas antes do início.');
        }

        if ($now->greaterThanOrEqualTo($appointment->occupied_until)) {
            $this->fail('status', 'O horário deste agendamento já terminou. Registre como não comparecimento.');
        }
    }

    /** @param  array<string, mixed>|null  $changes */
    private function record(
        Appointment $appointment,
        User $actor,
        AppointmentEventType $type,
        ?AppointmentStatus $from,
        ?AppointmentStatus $to,
        ?string $reason,
        ?array $changes = null,
    ): void {
        $appointment->events()->create([
            'company_id' => $appointment->company_id,
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor->id,
            'reason' => $reason,
            'changes' => $changes,
        ]);
    }

    /**
     * Translates the exclusion-constraint violation of a concurrent booking into 409.
     *
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    private function guardOverlap(Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23P01' && str_contains($exception->getMessage(), 'appointments_no_overlap')) {
                throw new ConflictHttpException('O profissional já possui um agendamento neste horário.', $exception);
            }

            throw $exception;
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
