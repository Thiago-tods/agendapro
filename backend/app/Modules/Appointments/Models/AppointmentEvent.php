<?php

namespace App\Modules\Appointments\Models;

use App\Models\User;
use App\Modules\Appointments\Enums\AppointmentEventType;
use App\Modules\Appointments\Enums\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail of an appointment.
 *
 * @property string $id
 * @property string $appointment_id
 * @property AppointmentEventType $type
 * @property AppointmentStatus|null $from_status
 * @property AppointmentStatus|null $to_status
 * @property string|null $actor_id
 * @property string|null $reason
 * @property array<string, mixed>|null $changes
 * @property CarbonImmutable $created_at
 */
class AppointmentEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => AppointmentEventType::class,
            'from_status' => AppointmentStatus::class,
            'to_status' => AppointmentStatus::class,
            'changes' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
