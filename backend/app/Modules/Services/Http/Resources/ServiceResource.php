<?php

namespace App\Modules\Services\Http\Resources;

use App\Modules\Professionals\Models\Professional;
use App\Modules\Services\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Service */
class ServiceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'price_cents' => $this->price_cents,
            'duration_minutes' => $this->duration_minutes,
            'buffer_minutes' => $this->buffer_minutes,
            'color' => $this->color,
            'is_active' => $this->is_active,
            'professionals_count' => $this->whenCounted('professionals'),
            'professionals' => $this->whenLoaded('professionals', fn () => $this->professionals->map(fn (Professional $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'color' => $p->color,
                'is_active' => $p->is_active,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
