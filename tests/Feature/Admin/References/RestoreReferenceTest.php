<?php

use App\Enums\AuditAction;
use App\Enums\DegreeProgram;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\LevelCredentialRequirement;
use App\Models\ProgramOffering;

beforeEach(function () {
    $this->admin = userWithRole(RoleName::Admin);
});

function makeOffering(Department $department): ProgramOffering
{
    return ProgramOffering::create([
        'department_id' => $department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);
}

it('lists trashed departments only when requested and restores them with an audit row', function () {
    $department = Department::create(['name' => 'Physics', 'code' => 'PHY']);
    $department->delete();

    $this->actingAs($this->admin)
        ->get(route('admin.references.departments.index'))
        ->assertInertia(fn ($page) => $page->has('departments', 0)->where('filters.trashed', false));

    $this->actingAs($this->admin)
        ->get(route('admin.references.departments.index', ['trashed' => 1]))
        ->assertInertia(fn ($page) => $page
            ->has('departments', 1)
            ->where('departments.0.id', $department->id)
            ->where('filters.trashed', true));

    $this->actingAs($this->admin)
        ->post(route('admin.references.departments.restore', $department))
        ->assertRedirect();

    expect($department->fresh()->trashed())->toBeFalse();

    expect(AuditLog::query()
        ->where('subject_type', $department->getMorphClass())
        ->where('subject_id', $department->id)
        ->where('action', AuditAction::Restored->value)
        ->exists())->toBeTrue();
});

it('refuses to restore an offering while its department is trashed, then allows it', function () {
    $department = Department::create(['name' => 'CS', 'code' => 'CS']);
    $offering = makeOffering($department);

    $offering->delete();
    $department->delete();

    $this->actingAs($this->admin)
        ->post(route('admin.references.program-offerings.restore', $offering))
        ->assertRedirect();
    expect($offering->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('admin.references.departments.restore', $department));

    $this->actingAs($this->admin)
        ->post(route('admin.references.program-offerings.restore', $offering))
        ->assertRedirect();
    expect($offering->fresh()->trashed())->toBeFalse();
});

it('restores trashed document types and level requirements', function () {
    $department = Department::create(['name' => 'CS', 'code' => 'CS']);
    $offering = makeOffering($department);
    $documentType = DocumentType::create(['name' => 'GCE A/L', 'code' => 'GCE_AL']);
    $requirement = LevelCredentialRequirement::create([
        'program_offering_id' => $offering->id,
        'level' => 1,
        'document_type_id' => $documentType->id,
        'required' => true,
    ]);

    $requirement->delete();
    $documentType->delete();

    $this->actingAs($this->admin)
        ->post(route('admin.references.level-requirements.restore', $requirement))
        ->assertRedirect();
    // Parent document type still trashed → refused.
    expect($requirement->fresh()->trashed())->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('admin.references.document-types.restore', $documentType));
    expect($documentType->fresh()->trashed())->toBeFalse();

    $this->actingAs($this->admin)
        ->post(route('admin.references.level-requirements.restore', $requirement));
    expect($requirement->fresh()->trashed())->toBeFalse();
});

it('forbids non-admins from restoring reference rows', function () {
    $department = Department::create(['name' => 'Chemistry', 'code' => 'CHM']);
    $department->delete();

    $this->actingAs(userWithRole(RoleName::Sao))
        ->post(route('admin.references.departments.restore', $department))
        ->assertForbidden();

    expect($department->fresh()->trashed())->toBeTrue();
});
