<?php

use App\Enums\DegreeProgram;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\LevelCredentialRequirement;
use App\Models\ProgramOffering;

beforeEach(function () {
    $this->admin = userWithRole(RoleName::Admin);
    $this->department = Department::create(['name' => 'Computer Science', 'code' => 'CS']);
    $this->offering = ProgramOffering::create([
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);
    $this->gceAl = DocumentType::create(['name' => 'GCE A/L', 'code' => 'GCE_AL']);
    $this->hnd = DocumentType::create(['name' => 'HND', 'code' => 'HND']);
});

it('admin can list level requirements with eager-loaded relations', function () {
    LevelCredentialRequirement::create([
        'program_offering_id' => $this->offering->id,
        'level' => 1,
        'document_type_id' => $this->gceAl->id,
        'required' => true,
    ]);

    $response = $this->actingAs($this->admin)->get('/admin/references/level-requirements');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/references/LevelRequirements')
        ->has('requirements', 1)
        ->where('requirements.0.program_offering.department.name', 'Computer Science')
        ->where('requirements.0.document_type.code', 'GCE_AL')
        ->has('offerings', 1)
        ->has('documentTypes', 2));
});

it('admin can create a level requirement within the offering range', function () {
    $response = $this->actingAs($this->admin)->post('/admin/references/level-requirements', [
        'program_offering_id' => $this->offering->id,
        'level' => 1,
        'document_type_id' => $this->gceAl->id,
        'required' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    expect(LevelCredentialRequirement::count())->toBe(1);
});

it('rejects a level above the offering max_level', function () {
    $response = $this->actingAs($this->admin)->post('/admin/references/level-requirements', [
        'program_offering_id' => $this->offering->id,
        'level' => 5,
        'document_type_id' => $this->gceAl->id,
        'required' => true,
    ]);

    $response->assertSessionHasErrors('level');
});

it('rejects a level below the offering min_level', function () {
    $offering = ProgramOffering::create([
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Masters->value,
        'min_level' => 5,
        'max_level' => 6,
    ]);

    $response = $this->actingAs($this->admin)->post('/admin/references/level-requirements', [
        'program_offering_id' => $offering->id,
        'level' => 1,
        'document_type_id' => $this->gceAl->id,
        'required' => true,
    ]);

    $response->assertSessionHasErrors('level');
});

it('rejects a duplicate (offering, level, document_type) tuple', function () {
    LevelCredentialRequirement::create([
        'program_offering_id' => $this->offering->id,
        'level' => 1,
        'document_type_id' => $this->gceAl->id,
        'required' => true,
    ]);

    $response = $this->actingAs($this->admin)->post('/admin/references/level-requirements', [
        'program_offering_id' => $this->offering->id,
        'level' => 1,
        'document_type_id' => $this->gceAl->id,
        'required' => false,
    ]);

    $response->assertSessionHasErrors('document_type_id');
});

it('allows the same document type at a different level for the same offering', function () {
    LevelCredentialRequirement::create([
        'program_offering_id' => $this->offering->id,
        'level' => 1,
        'document_type_id' => $this->gceAl->id,
        'required' => true,
    ]);

    $response = $this->actingAs($this->admin)->post('/admin/references/level-requirements', [
        'program_offering_id' => $this->offering->id,
        'level' => 3,
        'document_type_id' => $this->gceAl->id,
        'required' => true,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    expect(LevelCredentialRequirement::count())->toBe(2);
});

it('admin can update a level requirement', function () {
    $req = LevelCredentialRequirement::create([
        'program_offering_id' => $this->offering->id,
        'level' => 1,
        'document_type_id' => $this->gceAl->id,
        'required' => true,
    ]);

    $response = $this->actingAs($this->admin)->patch("/admin/references/level-requirements/{$req->id}", [
        'program_offering_id' => $this->offering->id,
        'level' => 2,
        'document_type_id' => $this->gceAl->id,
        'required' => false,
    ]);

    $response->assertRedirect();
    expect($req->fresh()->level)->toBe(2);
    expect($req->fresh()->required)->toBeFalse();
});

it('admin can soft-delete a level requirement', function () {
    $req = LevelCredentialRequirement::create([
        'program_offering_id' => $this->offering->id,
        'level' => 1,
        'document_type_id' => $this->gceAl->id,
        'required' => true,
    ]);

    $response = $this->actingAs($this->admin)->delete("/admin/references/level-requirements/{$req->id}");

    $response->assertRedirect();
    expect($req->fresh()->trashed())->toBeTrue();
});
