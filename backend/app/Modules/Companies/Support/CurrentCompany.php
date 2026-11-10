<?php

namespace App\Modules\Companies\Support;

use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMember;
use LogicException;

/**
 * Request-scoped tenant context. Filled by the EnsureCompanyMember middleware after the
 * authenticated user's membership in the route company has been verified.
 *
 * Company-owned queries must take the company from here, never from request input.
 */
class CurrentCompany
{
    private ?Company $company = null;

    private ?CompanyMember $membership = null;

    public function set(Company $company, CompanyMember $membership): void
    {
        $this->company = $company;
        $this->membership = $membership;
    }

    public function company(): Company
    {
        return $this->company ?? throw new LogicException('No company context. Is the company.member middleware applied?');
    }

    public function membership(): CompanyMember
    {
        return $this->membership ?? throw new LogicException('No company context. Is the company.member middleware applied?');
    }

    public function id(): string
    {
        return $this->company()->id;
    }

    public function has(): bool
    {
        return $this->company !== null;
    }
}
