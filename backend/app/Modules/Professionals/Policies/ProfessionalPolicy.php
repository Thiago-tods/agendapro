<?php

namespace App\Modules\Professionals\Policies;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Professionals\Models\Professional;

class ProfessionalPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $user->roleIn($company) !== null;
    }

    public function view(User $user, Professional $professional): bool
    {
        return $user->roleIn($professional->company_id) !== null;
    }

    public function create(User $user, Company $company): bool
    {
        return (bool) $user->roleIn($company)?->canManageCatalog();
    }

    public function update(User $user, Professional $professional): bool
    {
        return (bool) $user->roleIn($professional->company_id)?->canManageCatalog();
    }

    public function delete(User $user, Professional $professional): bool
    {
        return (bool) $user->roleIn($professional->company_id)?->canDeleteRecords();
    }
}
