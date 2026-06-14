<?php

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Models\Course;
use App\Models\CourseSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseSession>
 */
class CourseSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => fn () => Course::factory()->approved()->assigned(),
            'scheduled_for' => fake()->dateTimeBetween('-1 week', '+1 week'),
            'topic' => fake()->sentence(3),
            'duration_minutes' => fake()->numberBetween(60, 180),
            'status' => SessionStatus::Scheduled->value,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => SessionStatus::Scheduled->value,
        ]);
    }

    public function held(): static
    {
        return $this->state(fn (): array => [
            'status' => SessionStatus::Held->value,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => SessionStatus::Cancelled->value,
        ]);
    }
}
