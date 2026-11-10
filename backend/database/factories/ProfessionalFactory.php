<?php

namespace Database\Factories;

use App\Modules\Companies\Models\Company;
use App\Modules\Professionals\Models\Professional;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Professional>
 */
class ProfessionalFactory extends Factory
{
    protected $model = Professional::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'title' => fake()->randomElement(['Cabeleireira', 'Barbeiro', 'Fisioterapeuta', 'Esteticista', 'Consultor']),
            'bio' => null,
            'email' => fake()->unique()->safeEmail(),
            'phone' => '11'.fake()->numerify('9########'),
            'color' => fake()->randomElement(['#8B5CF6', '#7DD3FC', '#6EE7B7', '#FCD34D', '#FDA4AF']),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
