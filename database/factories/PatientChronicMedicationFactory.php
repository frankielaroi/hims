<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\PatientChronicMedication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PatientChronicMedication>
 */
class PatientChronicMedicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'drug_id' => null,
            'drug_name' => fake()->randomElement(['Metformin', 'Amlodipine', 'Lisinopril', 'Atenolol']),
            'strength' => fake()->randomElement(['5mg', '10mg', '500mg']),
            'dose' => '1 tablet',
            'route' => 'Oral',
            'frequency' => fake()->randomElement(['OD', 'BD', 'TDS']),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
