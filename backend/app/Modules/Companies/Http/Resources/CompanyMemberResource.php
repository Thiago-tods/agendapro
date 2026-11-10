<?php

namespace App\Modules\Companies\Http\Resources;

use App\Modules\Companies\Models\CompanyMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CompanyMember */
class CompanyMemberResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'joined_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
