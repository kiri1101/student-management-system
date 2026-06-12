<?php

use App\Enums\ApplicationStatus;
use App\Enums\DegreeProgram;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\LevelCredentialRequirement;
use App\Models\ProgramOffering;

beforeEach(function () {
    $this->admin = userWithRole(RoleName::Admin);
    $this->department = Department::create(['name' => 'Computer Science', 'code' => 'CS']);
});

it('admin can list program offerings with eager-loaded department', function () {
    ProgramOffering::create([
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);

    $response = $this->actingAs($this->admin)->get('/admin/references/program-offerings');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/references/ProgramOfferings')
        ->has('offerings', 1)
        ->has('departments', 1)
        ->has('degreePrograms', 3));
});

it('admin can create a program offering', function () {
    $response = $this->actingAs($this->admin)->post('/admin/references/program-offerings', [
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    expect(ProgramOffering::count())->toBe(1);
});

it('rejects an offering when (department, degree_program) already exists', function () {
    ProgramOffering::create([
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);

    $response = $this->actingAs($this->admin)->post('/admin/references/program-offerings', [
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 3,
    ]);

    $response->assertSessionHasErrors('degree_program');
});

it('allows the same degree program in a different department', function () {
    $other = Department::create(['name' => 'Mathematics', 'code' => 'MATH']);
    ProgramOffering::create([
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);

    $response = $this->actingAs($this->admin)->post('/admin/references/program-offerings', [
        'department_id' => $other->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    expect(ProgramOffering::count())->toBe(2);
});

it('rejects an offering when max_level is less than min_level', function () {
    $response = $this->actingAs($this->admin)->post('/admin/references/program-offerings', [
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 4,
        'max_level' => 1,
    ]);

    $response->assertSessionHasErrors('max_level');
});

it('admin can update a program offering', function () {
    $offering = ProgramOffering::create([
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);

    $response = $this->actingAs($this->admin)->patch("/admin/references/program-offerings/{$offering->id}", [
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 5,
    ]);

    $response->assertRedirect();
    expect($offering->fresh()->max_level)->toBe(5);
});

it('admin can soft-delete an offering with no requirements', function () {
    $offering = ProgramOffering::create([
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);

    $response = $this->actingAs($this->admin)->delete("/admin/references/program-offerings/{$offering->id}");

    $response->assertRedirect();
    expect($offering->fresh()->trashed())->toBeTrue();
});

it('refuses to delete an offering that still has requirements', function () {
    $offering = ProgramOffering::create([
        'department_id' => $this->department->id,
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

    $response = $this->actingAs($this->admin)->delete("/admin/references/program-offerings/{$offering->id}");

    $response->assertRedirect();
    expect($offering->fresh()->trashed())->toBeFalse();
});

it('refuses to delete an offering that applications reference', function () {
    $offering = ProgramOffering::create([
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);
    Application::factory()->submitted()->create(['program_offering_id' => $offering->id]);

    $response = $this->actingAs($this->admin)->delete("/admin/references/program-offerings/{$offering->id}");

    $response->assertRedirect();
    expect($offering->fresh()->trashed())->toBeFalse();
});

it('refuses to narrow the level range below an existing credential requirement', function () {
    $offering = ProgramOffering::create([
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);
    $doc = DocumentType::create(['name' => 'GCE A/L', 'code' => 'GCE_AL']);
    LevelCredentialRequirement::create([
        'program_offering_id' => $offering->id,
        'level' => 4,
        'document_type_id' => $doc->id,
        'required' => true,
    ]);

    $response = $this->actingAs($this->admin)->patch("/admin/references/program-offerings/{$offering->id}", [
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 3,
    ]);

    $response->assertSessionHasErrors('min_level');
    expect($offering->fresh()->max_level)->toBe(4);
});

it('refuses to narrow the level range past an open application', function () {
    $offering = ProgramOffering::create([
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);
    Application::factory()->submitted()->create([
        'program_offering_id' => $offering->id,
        'level' => 4,
    ]);

    $response = $this->actingAs($this->admin)->patch("/admin/references/program-offerings/{$offering->id}", [
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 3,
    ]);

    $response->assertSessionHasErrors('min_level');
    expect($offering->fresh()->max_level)->toBe(4);
});

it('allows narrowing past a decided application and widening over requirements', function () {
    $offering = ProgramOffering::create([
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);
    Application::factory()->create([
        'program_offering_id' => $offering->id,
        'level' => 4,
        'status' => ApplicationStatus::Rejected->value,
    ]);

    $response = $this->actingAs($this->admin)->patch("/admin/references/program-offerings/{$offering->id}", [
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 3,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    expect($offering->fresh()->max_level)->toBe(3);
});

it('blocks recreating a (department, degree_program) pair while a soft-deleted offering with that key still exists', function () {
    $first = ProgramOffering::create([
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);
    $first->delete();

    $response = $this->actingAs($this->admin)->post('/admin/references/program-offerings', [
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);

    $response->assertSessionHasErrors('degree_program');
    expect(ProgramOffering::query()->whereNull('deleted_at')->count())->toBe(0);
});
