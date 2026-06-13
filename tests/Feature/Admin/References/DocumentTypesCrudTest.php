<?php

use App\Enums\DegreeProgram;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\LevelCredentialRequirement;
use App\Models\ProgramOffering;

beforeEach(function () {
    $this->admin = userWithRole(RoleName::Admin);
});

it('admin can list document types', function () {
    DocumentType::create(['name' => 'National Identity', 'code' => 'NID']);

    $response = $this->actingAs($this->admin)->get('/admin/references/document-types');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/references/DocumentTypes')
        ->has('documentTypes', 1));
});

it('admin can create a document type', function () {
    $response = $this->actingAs($this->admin)->post('/admin/references/document-types', [
        'name' => 'GCE Advanced Level',
        'code' => 'GCE_AL',
    ]);

    $response->assertRedirect();
    expect(DocumentType::where('code', 'GCE_AL')->exists())->toBeTrue();
});

it('rejects a document type with a duplicate code', function () {
    DocumentType::create(['name' => 'National Identity', 'code' => 'NID']);

    $response = $this->actingAs($this->admin)->post('/admin/references/document-types', [
        'name' => 'National ID Card',
        'code' => 'NID',
    ]);

    $response->assertSessionHasErrors('code');
});

it('admin can update a document type', function () {
    $doc = DocumentType::create(['name' => 'NID', 'code' => 'NID']);

    $response = $this->actingAs($this->admin)->patch("/admin/references/document-types/{$doc->id}", [
        'name' => 'National Identity',
        'code' => 'NID',
    ]);

    $response->assertRedirect();
    expect($doc->fresh()->name)->toBe('National Identity');
});

it('admin can soft-delete an unreferenced document type', function () {
    $doc = DocumentType::create(['name' => 'GCE A/L', 'code' => 'GCE_AL']);

    $response = $this->actingAs($this->admin)->delete("/admin/references/document-types/{$doc->id}");

    $response->assertRedirect();
    expect($doc->fresh()->trashed())->toBeTrue();
});

it('refuses to delete an always-required document type', function (string $code) {
    $doc = DocumentType::create(['name' => $code, 'code' => $code]);

    $response = $this->actingAs($this->admin)->delete("/admin/references/document-types/{$doc->id}");

    $response->assertRedirect();
    expect($doc->fresh()->trashed())->toBeFalse();
})->with(DocumentType::PROTECTED_CODES);

it('refuses to rename the code of an always-required document type', function () {
    $doc = DocumentType::create(['name' => 'National Identity', 'code' => 'NID']);

    $response = $this->actingAs($this->admin)->patch("/admin/references/document-types/{$doc->id}", [
        'name' => 'National Identity',
        'code' => 'NID_RENAMED',
    ]);

    $response->assertSessionHasErrors('code');
    expect($doc->fresh()->code)->toBe('NID');
});

it('allows renaming the name (not code) of an always-required document type', function () {
    $doc = DocumentType::create(['name' => 'NID', 'code' => 'NID']);

    $response = $this->actingAs($this->admin)->patch("/admin/references/document-types/{$doc->id}", [
        'name' => 'National Identity Card',
        'code' => 'NID',
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors();
    expect($doc->fresh()->name)->toBe('National Identity Card');
});

it('refuses to delete a document type still referenced by a level requirement', function () {
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

    $response = $this->actingAs($this->admin)->delete("/admin/references/document-types/{$doc->id}");

    $response->assertRedirect();
    expect($doc->fresh()->trashed())->toBeFalse();
});
