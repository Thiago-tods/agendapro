<?php

namespace App\Modules\Appointments\Models;

use App\Models\User;
use App\Modules\Appointments\Enums\AppointmentStatus;
use App\Modules\Appointments\Policies\AppointmentPolicy;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Models\Customer;
use App\Modules\Professionals\Models\Professional;
use App\Modules\Services\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Never hard-deleted: cancellation is a status. All writes go through AppointmentService,
 * which validates availability and records an AppointmentEvent for every change.
 *
 * @property string $id
 * @property string $company_id
 * @property string $customer_id
 * @property string $professional_id
 * @property string $service_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable $occupied_until
 * @property AppointmentStatus $status
 * @property string $service_name
 * @property int $service_price_cents
 * @property int $service_duration_minutes
 * @property int $service_buffer_minutes
 * @property string|null $notes
 * @property CarbonImmutable|null $checked_in_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property string|null $cancelled_by
 * @property string|null $created_by
 */
#[UsePolicy(AppointmentPolicy::class)]
class Appointment extends Model
{
    use HasUuids;

    /** Only the service layer assigns attributes, explicitly. */
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'occupied_until' => 'immutable_datetime',
            'checked_in_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'service_price_cents' => 'integer',
            'service_duration_minutes' => 'integer',
            'service_buffer_minutes' => 'integer',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Professional, $this> */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<AppointmentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(AppointmentEvent::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Appointments whose occupied interval intersects [from, until). Half-open on both sides.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOccupyingBetween(Builder $query, CarbonImmutable $from, CarbonImmutable $until): void
    {
        $query->whereIn('status', AppointmentStatus::occupyingValues())
            ->where('starts_at', '<', $until->utc())
            ->where('occupied_until', '>', $from->utc());
    }

    /**
     * Appointments starting in [from, until).
     *
     * @param  Builder<self>  $query
     */
    public function scopeStartingBetween(Builder $query, CarbonImmutable $from, CarbonImmutable $until): void
    {
        // Always compare in UTC: Eloquent serializes dates without an offset.
        $query->where('appointments.starts_at', '>=', $from->utc())->where('appointments.starts_at', '<', $until->utc());
    }

    public function delete(): never
    {
        throw new \LogicException('Appointments are never deleted. Cancel them instead.');
    }

    public const CHECK_IN_WINDOW_MINUTES = 120;

    /**
     * Why moving to $target is not allowed at $now, or null when it is. Single source for the
     * status rules: AppointmentService enforces it and the API exposes it as available actions.
     */
    public function transitionBlocker(AppointmentStatus $target, CarbonImmutable $now): ?string
    {
        if (! $this->status->canTransitionTo($target)) {
            return "Não é possível passar de \"{$this->status->label()}\" para \"{$target->label()}\".";
        }

        return match ($target) {
            AppointmentStatus::CheckedIn => match (true) {
                $now->lessThan($this->starts_at->subMinutes(self::CHECK_IN_WINDOW_MINUTES)) => 'O check-in só pode ser feito a partir de 2 horas antes do início.',
                $now->greaterThanOrEqualTo($this->occupied_until) => 'O horário deste agendamento já terminou. Registre como não comparecimento.',
                default => null,
            },
            AppointmentStatus::NoShow => $now->lessThan($this->starts_at)
                ? 'Só é possível registrar o não comparecimento após o horário de início.'
                : null,
            default => null,
        };
    }

    /**
     * Actions the current state and time allow (permissions are checked separately).
     *
     * @return list<string>
     */
    public function availableActions(CarbonImmutable $now): array
    {
        $actions = [
            'confirm' => AppointmentStatus::Confirmed,
            'check_in' => AppointmentStatus::CheckedIn,
            'complete' => AppointmentStatus::Completed,
            'no_show' => AppointmentStatus::NoShow,
            'cancel' => AppointmentStatus::Cancelled,
        ];

        $available = array_keys(array_filter($actions, fn (AppointmentStatus $target): bool => $this->transitionBlocker($target, $now) === null));

        if ($this->status->isReschedulable()) {
            $available[] = 'reschedule';
        }

        return $available;
    }
}
