<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\LecturerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LecturerProfile>
 */
class LecturerProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'department_id' => fn () => Department::firstOrCreate(
                ['code' => 'CS'],
                ['name' => 'Computer Science'],
            )->id,
            'specialization' => fake()->word(),
            'hired_at' => now()->subYears(2)->toDateString(),
        ];
    }
}
