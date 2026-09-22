<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hospital_id' => 'HIMS-'.date('Y').'-'.fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-1 year')->format('Y-m-d'),
            'sex' => fake()->randomElement(['male', 'female']),
            'phone_number' => fake()->numerify('02########'),
            'payment_method' => 'cash',
        ];
    }

    public function female(): static
    {
        return $this->state(fn (array $attributes) => ['sex' => 'female']);
    }

    public function nhis(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'nhis',
            'nhis_number' => fake()->numerify('########'),
            'nhis_expiry' => now()->addYear()->format('Y-m-d'),
        ]);
    }
}
