<?php

namespace App\Modules\Appointments\Http\Resources;

use App\Modules\Appointments\Models\Appointment;
use App\Modules\Appointments\Models\AppointmentEvent;
use App\Modules\Companies\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Instants are UTC (ISO 8601, "Z"). The "local" block is the same interval in the company's
 * timezone, ready for display.
 *
 * @mixin Appointment
 */
class AppointmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // Appointments are only served inside a company context, so the tenant is already known.
        $timezone = app(CurrentCompany::class)->company()->timezone;
        $localStart = $this->starts_at->setTimezone($timezone);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'starts_at' => $this->starts_at->toIso8601ZuluString(),
            'ends_at' => $this->ends_at->toIso8601ZuluString(),
            'occupied_until' => $this->occupied_until->toIso8601ZuluString(),
            'local' => [
                'timezone' => $timezone,
                'date' => $localStart->toDateString(),
                'start' => $localStart->format('H:i'),
                'end' => $this->ends_at->setTimezone($timezone)->format('H:i'),
            ],
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ]),
            'professional' => $this->whenLoaded('professional', fn () => [
                'id' => $this->professional->id,
                'name' => $this->professional->name,
                'color' => $this->professional->color,
            ]),
            // Snapshot taken at booking time; service_id still points at the catalog entry.
            'service' => [
                'id' => $this->service_id,
                'name' => $this->service_name,
                'price_cents' => $this->service_price_cents,
                'duration_minutes' => $this->service_duration_minutes,
                'buffer_minutes' => $this->service_buffer_minutes,
            ],
            'notes' => $this->notes,
            // What the state and the clock allow now; the SPA shows only these (permissions apart).
            'available_actions' => $this->availableActions(CarbonImmutable::now()),
            'checked_in_at' => $this->checked_in_at?->toIso8601ZuluString(),
            'completed_at' => $this->completed_at?->toIso8601ZuluString(),
            'cancelled_at' => $this->cancelled_at?->toIso8601ZuluString(),
            'cancellation_reason' => $this->cancellation_reason,
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn (AppointmentEvent $e): array => [
                'id' => $e->id,
                'type' => $e->type->value,
                'label' => $e->type->label(),
                'from_status' => $e->from_status?->value,
                'to_status' => $e->to_status?->value,
                'reason' => $e->reason,
                'changes' => $e->changes,
                'actor' => $e->actor ? ['id' => $e->actor->id, 'name' => $e->actor->name] : null,
                'created_at' => $e->created_at->toIso8601ZuluString(),
            ])->values()),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
