<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'department_id' => Department::factory(),
            'visit_number' => 'V-'.date('Ymd').'-'.fake()->unique()->numerify('####'),
            'queue_number' => fake()->numberBetween(1, 200),
            'type' => 'OPD',
            'priority' => 'routine',
            'payment_method' => 'cash',
            'status' => 'waiting_doctor',
            'created_by' => User::factory(),
        ];
    }

    /**
     * A visit sitting in the doctor's queue.
     */
    public function waitingDoctor(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'waiting_doctor']);
    }

    public function emergency(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'emergency',
            'type' => 'Emergency',
        ]);
    }
}
