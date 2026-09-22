<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientAllergy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientAllergy>
 */
class PatientAllergyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'allergen' => fake()->randomElement(['Penicillin', 'Sulfa', 'Aspirin', 'Peanuts', 'Codeine']),
            'reaction' => fake()->randomElement(['Rash', 'Urticaria', 'Angioedema', 'Anaphylaxis']),
            'severity' => 'moderate',
            'status' => 'active',
        ];
    }

    public function severe(): static
    {
        return $this->state(fn (array $attributes) => ['severity' => 'severe']);
    }

    public function lifeThreatening(): static
    {
        return $this->state(fn (array $attributes) => ['severity' => 'life-threatening']);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'resolved']);
    }

    public function to(string $allergen): static
    {
        return $this->state(fn (array $attributes) => ['allergen' => $allergen]);
    }
}
