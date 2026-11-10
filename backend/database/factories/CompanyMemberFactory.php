<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Models\CompanyMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyMember>
 */
class CompanyMemberFactory extends Factory
{
    protected $model = CompanyMember::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'role' => CompanyRole::Professional,
        ];
    }

    public function role(CompanyRole $role): static
    {
        return $this->state(['role' => $role]);
    }
}
