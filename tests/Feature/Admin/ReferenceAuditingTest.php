<?php

use App\Enums\AuditAction;
use App\Enums\DegreeProgram;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\LevelCredentialRequirement;
use App\Models\ProgramOffering;

it('records a Created audit log when a Department is created', function () {
    $department = Department::create(['name' => 'CS', 'code' => 'CS']);

    expect(AuditLog::query()
        ->where('subject_type', $department->getMorphClass())
        ->where('subject_id', $department->id)
        ->where('action', AuditAction::Created->value)
        ->exists())->toBeTrue();
});

it('records an Updated audit log when a ProgramOffering is updated', function () {
    $department = Department::create(['name' => 'CS', 'code' => 'CS']);
    $offering = ProgramOffering::create([
        'department_id' => $department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);

    $offering->update(['max_level' => 5]);

    $log = AuditLog::query()
        ->where('subject_type', $offering->getMorphClass())
        ->where('subject_id', $offering->id)
        ->where('action', AuditAction::Updated->value)
        ->sole();

    expect($log->changes['before']['max_level'])->toBe(4)
        ->and($log->changes['after']['max_level'])->toBe(5);
});

it('records a Deleted audit log when a DocumentType is soft-deleted', function () {
    $type = DocumentType::create(['name' => 'GCE A/L', 'code' => 'GCE_AL']);

    $type->delete();

    expect(AuditLog::query()
        ->where('subject_type', $type->getMorphClass())
        ->where('subject_id', $type->id)
        ->where('action', AuditAction::Deleted->value)
        ->exists())->toBeTrue();
});

it('records a Restored audit log when a LevelCredentialRequirement is restored', function () {
    $department = Department::create(['name' => 'CS', 'code' => 'CS']);
    $offering = ProgramOffering::create([
        'department_id' => $department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);
    $type = DocumentType::create(['name' => 'GCE A/L', 'code' => 'GCE_AL']);

    $requirement = LevelCredentialRequirement::create([
        'program_offering_id' => $offering->id,
        'level' => 1,
        'document_type_id' => $type->id,
        'required' => true,
    ]);

    $requirement->delete();
    $requirement->restore();

    expect(AuditLog::query()
        ->where('subject_type', $requirement->getMorphClass())
        ->where('subject_id', $requirement->id)
        ->where('action', AuditAction::Restored->value)
        ->exists())->toBeTrue();
});
