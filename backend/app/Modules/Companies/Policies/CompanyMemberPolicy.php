<?php

namespace App\Modules\Companies\Policies;

use App\Models\User;
use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Companies\Models\CompanyMember;

class CompanyMemberPolicy
{
    public function update(User $user, CompanyMember $member, CompanyRole $newRole): bool
    {
        $actor = $this->actorIn($user, $member);

        return $actor !== null && $actor->role->canAdministrate($member->role, $newRole);
    }

    public function delete(User $user, CompanyMember $member): bool
    {
        $actor = $this->actorIn($user, $member);

        return $actor !== null && $actor->role->canAdministrate($member->role);
    }

    /** The acting user's membership in the same company as $member (never across companies). */
    private function actorIn(User $user, CompanyMember $member): ?CompanyMember
    {
        return $user->memberships()->where('company_id', $member->company_id)->first();
    }
}
