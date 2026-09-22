<?php

namespace Database\Factories;

use App\Models\Drug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Drug>
 */
class DrugFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' '.fake()->numberBetween(100, 500).'mg',
            'generic_name' => fake()->word(),
            'strength' => fake()->numberBetween(100, 500).'mg',
            'formulation' => 'Tablet',
            'drug_class' => null,
            'is_nhis_covered' => true,
            'cash_price' => fake()->randomFloat(2, 1, 40),
            'nhis_price' => fake()->randomFloat(2, 1, 30),
            'stock_level' => 500,
            'is_active' => true,
        ];
    }

    /**
     * A named drug, so allergy matching can be asserted against real names.
     */
    public function named(string $name, string $genericName, ?string $drugClass = null): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'generic_name' => $genericName,
            'drug_class' => $drugClass,
        ]);
    }
}
