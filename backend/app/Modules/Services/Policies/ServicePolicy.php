<?php

namespace App\Modules\Services\Policies;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Services\Models\Service;

class ServicePolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $user->roleIn($company) !== null;
    }

    public function view(User $user, Service $service): bool
    {
        return $user->roleIn($service->company_id) !== null;
    }

    public function create(User $user, Company $company): bool
    {
        return (bool) $user->roleIn($company)?->canManageCatalog();
    }

    public function update(User $user, Service $service): bool
    {
        return (bool) $user->roleIn($service->company_id)?->canManageCatalog();
    }

    public function delete(User $user, Service $service): bool
    {
        return (bool) $user->roleIn($service->company_id)?->canDeleteRecords();
    }
}
