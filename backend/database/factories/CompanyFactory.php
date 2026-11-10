<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Companies\Enums\CompanyRole;
use App\Modules\Companies\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'timezone' => 'America/Sao_Paulo',
            'currency' => 'BRL',
            'locale' => 'pt_BR',
        ];
    }

    /** Attach a user to the company with the given role after creation. */
    public function withMember(User $user, CompanyRole $role): static
    {
        return $this->afterCreating(
            fn (Company $company) => $company->members()->create(['user_id' => $user->id, 'role' => $role]),
        );
    }
}
