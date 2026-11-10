<?php

namespace App\Modules\Companies\Http\Resources;

use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Company */
class CompanyResource extends JsonResource
{
    /** Membership of the requesting user, when known. */
    private ?CompanyMember $membership = null;

    public function withMembership(?CompanyMember $membership): static
    {
        $this->membership = $membership;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $role = $this->membership?->role
            ?? ($this->resource->pivot instanceof CompanyMember ? $this->resource->pivot->role : null);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'membership' => $role instanceof CompanyRole ? [
                'role' => $role->value,
                'role_label' => $role->label(),
                // UI hints only (show/hide actions). The API enforces every rule on its own.
                'permissions' => $role->permissions(),
            ] : null,
        ];
    }
}
