<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word()).' Clinic';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $name.' department',
            'is_active' => true,
        ];
    }
}
