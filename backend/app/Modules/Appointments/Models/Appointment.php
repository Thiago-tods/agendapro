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
            ->where('starts_at', '<', $until)
            ->where('occupied_until', '>', $from);
    }

    public function delete(): never
    {
        throw new \LogicException('Appointments are never deleted. Cancel them instead.');
    }
}
