<?php

namespace Database\Factories;

use App\Enums\DegreeProgram;
use App\Enums\StudentStatus;
use App\Models\Department;
use App\Models\ProgramOffering;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'matricule' => 'stm-'.fake()->unique()->numerify('######'),
            'program_offering_id' => fn () => $this->resolveDefaultOffering()->id,
            'level' => 100,
            'academic_year' => '2025-2026',
            'enrolled_at' => now()->toDateString(),
            'status' => StudentStatus::Active->value,
        ];
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
