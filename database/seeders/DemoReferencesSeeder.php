<?php

namespace Database\Seeders;

use App\Enums\DegreeProgram;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\LevelCredentialRequirement;
use App\Models\ProgramOffering;
use Illuminate\Database\Seeder;

/**
 * Demo data for local dev only — Computer Science department with HND,
 * Bachelors, and Masters offerings, plus their entry-level credential rules.
 * National Identity + Birth Certificate are always-required at the form level
 * (per `plan/context.md` §4.9) and are intentionally not seeded here.
 */
class DemoReferencesSeeder extends Seeder
{
    public function run(): void
    {
        $department = Department::query()->firstOrCreate(
            ['code' => 'CS'],
            ['name' => 'Computer Science', 'description' => 'Computing and information sciences.'],
        );

        $offerings = [
            DegreeProgram::Hnd->value => ['min' => 1, 'max' => 2],
            DegreeProgram::Bachelors->value => ['min' => 1, 'max' => 4],
            DegreeProgram::Masters->value => ['min' => 1, 'max' => 2],
        ];

        $offeringIds = [];
        foreach ($offerings as $program => $range) {
            $offering = ProgramOffering::query()->firstOrCreate(
                ['department_id' => $department->id, 'degree_program' => $program],
                ['min_level' => $range['min'], 'max_level' => $range['max']],
            );
            $offeringIds[$program] = $offering->id;
        }

        $documentTypes = DocumentType::query()
            ->whereIn('code', ['GCE_AL', 'HND', 'BACH'])
            ->pluck('id', 'code');

        $rules = [
            [DegreeProgram::Hnd->value, 1, 'GCE_AL', 'Entry credential for level 1 HND.'],
            [DegreeProgram::Bachelors->value, 1, 'GCE_AL', 'Entry credential for level 1 Bachelors.'],
            [DegreeProgram::Bachelors->value, 3, 'HND', 'Lateral entry — HND holders enter at level 3.'],
            [DegreeProgram::Masters->value, 1, 'BACH', 'Entry credential for level 1 Masters.'],
        ];

        foreach ($rules as [$program, $level, $documentCode, $notes]) {
            LevelCredentialRequirement::query()->firstOrCreate(
                [
                    'program_offering_id' => $offeringIds[$program],
                    'level' => $level,
                    'document_type_id' => $documentTypes[$documentCode],
                ],
                ['required' => true, 'notes' => $notes],
            );
        }
    }
}
