<?php

namespace App\Modules\Customers\Policies;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Models\Customer;

/**
 * Roles are always read from the company that owns the record, never from the request.
 */
class CustomerPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $user->roleIn($company) !== null;
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->roleIn($customer->company_id) !== null;
    }

    public function create(User $user, Company $company): bool
    {
        return (bool) $user->roleIn($company)?->canManageCustomers();
    }

    public function update(User $user, Customer $customer): bool
    {
        return (bool) $user->roleIn($customer->company_id)?->canManageCustomers();
    }

    public function archive(User $user, Customer $customer): bool
    {
        return $this->update($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return (bool) $user->roleIn($customer->company_id)?->canDeleteRecords();
    }
}
