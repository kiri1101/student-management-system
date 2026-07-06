<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Enums\DegreeProgram;
use App\Models\Application;
use App\Models\Department;
use App\Models\ProgramOffering;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'program_offering_id' => fn () => $this->resolveDefaultOffering()->id,
            'level' => 1,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'contact_email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('6########'),
            'date_of_birth' => fake()->date(),
            'previous_institute' => fake()->company(),
            'status' => ApplicationStatus::Submitted->value,
            'submitted_at' => now(),
            'decided_at' => null,
            'decided_by_user_id' => null,
            'decision_notes' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => ApplicationStatus::Submitted->value,
            'submitted_at' => now(),
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
            ['min_level' => 1, 'max_level' => 4],
        );
    }
}
