<?php

namespace Database\Factories;

use App\Enums\DegreeProgram;
use App\Models\Department;
use App\Models\FeeSchedule;
use App\Models\ProgramOffering;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeSchedule>
 */
class FeeScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_offering_id' => fn () => $this->resolveDefaultOffering()->id,
            'level' => 1,
            'academic_year' => (string) now()->year,
            'total_xaf' => fake()->numberBetween(50, 600) * 1000,
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
            ['min_level' => 1, 'max_level' => 4],
        );
    }
}
