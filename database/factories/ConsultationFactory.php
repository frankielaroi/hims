<?php

namespace Database\Factories;

use App\Models\Consultation;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consultation>
 */
class ConsultationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'doctor_id' => User::factory(),
            'status' => Consultation::STATUS_IN_PROGRESS,
            'version' => 1,
        ];
    }

    /**
     * Held by a clinician with a warm editing lock.
     */
    public function lockedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'locked_by' => $user->id,
            'locked_at' => now(),
        ]);
    }

    /**
     * Lock left behind by a clinician who never came back.
     */
    public function staleLockBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'locked_by' => $user->id,
            'locked_at' => now()->subMinutes(Consultation::LOCK_TIMEOUT_MINUTES + 5),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Consultation::STATUS_DRAFT]);
    }

    public function signedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Consultation::STATUS_SIGNED,
            'signed_by' => $user->id,
            'signed_at' => now(),
            'finished_at' => now(),
        ]);
    }
}
