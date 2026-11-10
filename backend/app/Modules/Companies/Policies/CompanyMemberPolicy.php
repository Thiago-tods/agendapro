<?php

namespace App\Modules\Companies\Policies;

use App\Models\User;
use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMember;

class CompanyMemberPolicy
{
    /** Adding someone follows the same rule as assigning them that role. */
    public function create(User $user, Company $company, CompanyRole $role): bool
    {
        return (bool) $user->roleIn($company)?->canAdministrate($role, $role);
    }

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
