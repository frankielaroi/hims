<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\NewbornRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewbornRecord>
 */
class NewbornRecordFactory extends Factory
{
    protected $model = NewbornRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_id' => Delivery::factory(),
            'birth_order' => 1,
            'sex' => fake()->randomElement(['male', 'female']),
            'birth_weight_grams' => 3200,
            'apgar_one_minute' => 8,
            'apgar_five_minutes' => 9,
            'outcome' => NewbornRecord::OUTCOME_LIVE_BIRTH,
            'breastfed_within_hour' => true,
            'vitamin_k_given' => true,
            'eye_prophylaxis_given' => true,
        ];
    }

    public function stillbirth(): static
    {
        return $this->state(fn (array $attributes) => [
            'outcome' => NewbornRecord::OUTCOME_FRESH_STILLBIRTH,
            'apgar_one_minute' => 0,
            'apgar_five_minutes' => 0,
            'breastfed_within_hour' => false,
        ]);
    }

    public function lowBirthWeight(): static
    {
        return $this->state(fn (array $attributes) => ['birth_weight_grams' => 2100]);
    }
}
