<?php

namespace App\Modules\Professionals\Http\Resources;

use App\Modules\Professionals\Models\Professional;
use App\Modules\Services\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Professional */
class ProfessionalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->title,
            'bio' => $this->bio,
            'email' => $this->email,
            'phone' => $this->phone,
            'color' => $this->color,
            'is_active' => $this->is_active,
            'services_count' => $this->whenCounted('services'),
            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn (Service $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'color' => $s->color,
                'is_active' => $s->is_active,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
