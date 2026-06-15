<?php

namespace Database\Factories;

use App\Enums\DisputeStatus;
use App\Models\CourseResult;
use App\Models\ResultDispute;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultDispute>
 */
class ResultDisputeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_result_id' => fn () => CourseResult::factory()->published(),
            'student_profile_id' => fn () => StudentProfile::factory(),
            'reason' => fake()->sentence(),
            'status' => DisputeStatus::Open->value,
            'resolution_notes' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function underReview(): static
    {
        return $this->state(fn (): array => [
            'status' => DisputeStatus::UnderReview->value,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => DisputeStatus::Resolved->value,
            'resolution_notes' => fake()->sentence(),
            'reviewed_by' => fn () => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => DisputeStatus::Rejected->value,
            'resolution_notes' => fake()->sentence(),
            'reviewed_by' => fn () => User::factory(),
            'reviewed_at' => now(),
        ]);
    }
}
