<?php

namespace App\Modules\Companies\Policies;

use App\Models\User;
use App\Modules\Companies\Models\Company;

class CompanyPolicy
{
    public function view(User $user, Company $company): bool
    {
        return $user->membershipIn($company) !== null;
    }

    public function update(User $user, Company $company): bool
    {
        return (bool) $user->membershipIn($company)?->role->canManageCompany();
    }

    public function manageAvailability(User $user, Company $company): bool
    {
        return (bool) $user->roleIn($company)?->canManageAvailability();
    }

    public function viewMembers(User $user, Company $company): bool
    {
        return (bool) $user->membershipIn($company)?->role->canManageMembers();
    }
}
