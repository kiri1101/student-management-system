<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => fn () => Course::factory()->approved()->assigned(),
            'title' => fake()->sentence(3),
            'instructions' => fake()->paragraph(),
            'due_at' => fake()->dateTimeBetween('+1 day', '+2 weeks'),
            'max_score' => 20,
            'created_by' => fn () => User::factory(),
        ];
    }
}
