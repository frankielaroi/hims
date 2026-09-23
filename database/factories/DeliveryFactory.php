<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $visit = Visit::factory();

        return [
            'patient_id' => Patient::factory()->female(),
            'visit_id' => $visit,
            'admitted_in_labour_at' => now()->subHours(4),
            'gestation_weeks' => 39,
            'status' => Delivery::STATUS_IN_LABOUR,
            'recorded_by' => User::factory(),
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Delivery::STATUS_DELIVERED,
            'delivered_at' => now(),
            'mode' => 'spontaneous_vertex',
            'uterotonic_given' => true,
            'third_stage_management' => 'active',
            'placenta_state' => 'complete',
            'blood_loss_ml' => 250,
        ]);
    }
}
