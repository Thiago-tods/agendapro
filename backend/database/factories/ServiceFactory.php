<?php

namespace Database\Factories;

use App\Modules\Companies\Models\Company;
use App\Modules\Services\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->randomElement(['Corte', 'Escova', 'Barba', 'Avaliação', 'Sessão']).' '.fake()->unique()->numberBetween(1, 99999),
            'description' => null,
            'category' => fake()->randomElement(['Cabelo', 'Barba', 'Estética', null]),
            'price_cents' => fake()->numberBetween(30, 300) * 100,
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
            'buffer_minutes' => fake()->randomElement([0, 5, 10, 15]),
            'color' => fake()->randomElement(['#8B5CF6', '#7DD3FC', '#6EE7B7', '#FCD34D', '#FDA4AF']),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
