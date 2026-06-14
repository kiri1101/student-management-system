<?php

namespace Database\Factories;

use App\Enums\CoursePlanStatus;
use App\Enums\DegreeProgram;
use App\Models\Course;
use App\Models\Department;
use App\Models\LecturerProfile;
use App\Models\ProgramOffering;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_offering_id' => fn () => $this->resolveDefaultOffering()->id,
            'level' => 100,
            'academic_year' => (string) now()->year,
            'code' => fake()->unique()->bothify('???###'),
            'title' => fake()->sentence(3),
            'credits' => fake()->numberBetween(2, 6),
            'semester' => fake()->numberBetween(1, 2),
            'description' => fake()->paragraph(),
            'lecturer_profile_id' => null,
            'plan_status' => CoursePlanStatus::Draft->value,
            'plan_submitted_at' => null,
            'plan_reviewed_at' => null,
            'plan_reviewed_by' => null,
            'plan_review_notes' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'plan_status' => CoursePlanStatus::Draft->value,
            'plan_submitted_at' => null,
            'plan_reviewed_at' => null,
            'plan_reviewed_by' => null,
            'plan_review_notes' => null,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'plan_status' => CoursePlanStatus::Submitted->value,
            'plan_submitted_at' => now(),
            'plan_reviewed_at' => null,
            'plan_reviewed_by' => null,
            'plan_review_notes' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'plan_status' => CoursePlanStatus::Approved->value,
            'plan_submitted_at' => now()->subDay(),
            'plan_reviewed_at' => now(),
            'plan_reviewed_by' => User::factory(),
            'plan_review_notes' => null,
        ]);
    }

    /**
     * Attach a lecturer so the course is assigned (drafting can begin).
     */
    public function assigned(): static
    {
        return $this->state(fn (): array => [
            'lecturer_profile_id' => LecturerProfile::factory(),
        ]);
    }

    private function resolveDefaultOffering(): ProgramOffering
    {
        $department = Department::firstOrCreate(
            ['code' => 'CS'],
            ['name' => 'Computer Science'],
        );

        return ProgramOffering::firstOrCreate(
            ['department_id' => $department->id, 'degree_program' => DegreeProgram::Bachelors->value],
            ['min_level' => 100, 'max_level' => 400],
        );
    }
}
