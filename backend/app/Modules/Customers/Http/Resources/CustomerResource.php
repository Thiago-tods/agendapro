<?php

namespace App\Modules\Customers\Http\Resources;

use App\Modules\Customers\Models\Customer;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    /** Aggregates come back from PostgreSQL as UTC strings without an offset. */
    private static function iso(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value, 'UTC')->toIso8601String();
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'notes' => $this->notes,
            // Present when loaded through withVisitStats(): completed visits and the next active booking.
            'visits_count' => $this->whenHas('visits_count', fn () => (int) $this->visits_count),
            'last_visit_at' => $this->whenHas('last_visit_at', fn () => self::iso($this->last_visit_at)),
            'next_appointment_at' => $this->whenHas('next_appointment_at', fn () => self::iso($this->next_appointment_at)),
            'is_archived' => $this->isArchived(),
            'archived_at' => $this->archived_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
