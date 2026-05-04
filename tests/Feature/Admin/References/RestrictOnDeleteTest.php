<?php

use App\Enums\DegreeProgram;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\LevelCredentialRequirement;
use App\Models\ProgramOffering;
use Illuminate\Database\QueryException;

it('blocks force-deleting a department that still has program offerings', function () {
    $dept = Department::create(['name' => 'CS', 'code' => 'CS']);
    ProgramOffering::create([
        'department_id' => $dept->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);

    expect(fn () => $dept->forceDelete())->toThrow(QueryException::class);
});

it('blocks force-deleting a program offering that still has requirements', function () {
    $dept = Department::create(['name' => 'CS', 'code' => 'CS']);
    $offering = ProgramOffering::create([
        'department_id' => $dept->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);
    $doc = DocumentType::create(['name' => 'GCE A/L', 'code' => 'GCE_AL']);
    LevelCredentialRequirement::create([
        'program_offering_id' => $offering->id,
        'level' => 1,
        'document_type_id' => $doc->id,
        'required' => true,
    ]);

    expect(fn () => $offering->forceDelete())->toThrow(QueryException::class);
});

it('blocks force-deleting a document type still referenced by a requirement', function () {
    $dept = Department::create(['name' => 'CS', 'code' => 'CS']);
    $offering = ProgramOffering::create([
        'department_id' => $dept->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);
    $doc = DocumentType::create(['name' => 'GCE A/L', 'code' => 'GCE_AL']);
    LevelCredentialRequirement::create([
        'program_offering_id' => $offering->id,
        'level' => 1,
        'document_type_id' => $doc->id,
        'required' => true,
    ]);

    expect(fn () => $doc->forceDelete())->toThrow(QueryException::class);
});
