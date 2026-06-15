<?php

namespace Database\Factories;

use App\Enums\AssignmentSubmissionStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentSubmission>
 */
class AssignmentSubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => fn () => Assignment::factory(),
            'student_profile_id' => fn () => StudentProfile::factory(),
            'file_path' => 'assignment-submissions/'.fake()->uuid().'.pdf',
            'original_filename' => 'submission.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1000, 500000),
            'submitted_at' => now(),
            'is_late' => false,
            'score' => null,
            'feedback' => null,
            'graded_by' => null,
            'graded_at' => null,
            'status' => AssignmentSubmissionStatus::Submitted->value,
        ];
    }

    public function graded(): static
    {
        return $this->state(fn (): array => [
            'score' => 15,
            'feedback' => fake()->sentence(),
            'graded_by' => fn () => User::factory(),
            'graded_at' => now(),
            'status' => AssignmentSubmissionStatus::Graded->value,
        ]);
    }

    public function late(): static
    {
        return $this->state(fn (): array => [
            'is_late' => true,
        ]);
    }
}
