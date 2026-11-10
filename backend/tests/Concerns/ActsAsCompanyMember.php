<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Companies\Models\Company;

/**
 * Creates a company ("own") plus an unrelated one ("other"), and authenticates a member
 * of the own company with the requested role.
 */
trait ActsAsCompanyMember
{
    protected Company $company;

    protected Company $otherCompany;

    protected function setUpCompanies(): void
    {
        $this->company = Company::factory()->create();
        $this->otherCompany = Company::factory()->create();
    }

    protected function actAs(CompanyRole $role): User
    {
        $user = User::factory()->create();
        $this->company->members()->create(['user_id' => $user->id, 'role' => $role]);
        $this->actingAs($user);

        return $user;
    }

    protected function companyUrl(string $path = '', ?Company $company = null): string
    {
        return '/api/v1/companies/'.($company ?? $this->company)->id.$path;
    }

    /** @return array<string, array{CompanyRole}> */
    public static function allRoles(): array
    {
        return array_combine(
            array_map(fn (CompanyRole $r): string => $r->value, CompanyRole::cases()),
            array_map(fn (CompanyRole $r): array => [$r], CompanyRole::cases()),
        );
    }
}
