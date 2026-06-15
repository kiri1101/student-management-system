<?php

namespace Database\Factories;

use App\Enums\ResultStatus;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseResult>
 */
class CourseResultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => fn () => Course::factory()->approved(),
            'student_profile_id' => fn () => StudentProfile::factory(),
            'ca_score' => fake()->numberBetween(0, 100),
            'exam_score' => fake()->numberBetween(0, 100),
            'status' => ResultStatus::Draft->value,
            'published_at' => null,
            'published_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => ResultStatus::Published->value,
            'published_at' => now(),
            'published_by' => fn () => User::factory(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => ResultStatus::Draft->value,
            'published_at' => null,
            'published_by' => null,
        ]);
    }

    public function unscored(): static
    {
        return $this->state(fn (): array => [
            'ca_score' => null,
            'exam_score' => null,
        ]);
    }
}
