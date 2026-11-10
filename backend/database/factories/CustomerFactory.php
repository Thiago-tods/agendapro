<?php

namespace Database\Factories;

use App\Modules\Companies\Models\Company;
use App\Modules\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '11'.fake()->numerify('9########'),
            'birth_date' => fake()->optional()->dateTimeBetween('-80 years', '-16 years')?->format('Y-m-d'),
            'notes' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(['archived_at' => now()]);
    }
}
