<?php

use App\Enums\DegreeProgram;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\ProgramOffering;

beforeEach(function () {
    $this->admin = userWithRole(RoleName::Admin);
});

it('admin can list departments', function () {
    Department::create(['name' => 'Computer Science', 'code' => 'CS']);

    $response = $this->actingAs($this->admin)->get('/admin/references/departments');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/references/Departments')
        ->has('departments', 1)
        ->where('departments.0.name', 'Computer Science'));
});

it('admin can create a department', function () {
    $response = $this->actingAs($this->admin)->post('/admin/references/departments', [
        'name' => 'Mathematics',
        'code' => 'MATH',
        'description' => 'Pure and applied mathematics.',
    ]);

    $response->assertRedirect();
    expect(Department::where('code', 'MATH')->exists())->toBeTrue();
});

it('rejects a department with a duplicate name', function () {
    Department::create(['name' => 'Computer Science', 'code' => 'CS']);

    $response = $this->actingAs($this->admin)->post('/admin/references/departments', [
        'name' => 'Computer Science',
        'code' => 'CSX',
    ]);

    $response->assertSessionHasErrors('name');
});

it('admin can update a department', function () {
    $dept = Department::create(['name' => 'CS', 'code' => 'CS']);

    $response = $this->actingAs($this->admin)->patch("/admin/references/departments/{$dept->id}", [
        'name' => 'Computer Science',
        'code' => 'CS',
    ]);

    $response->assertRedirect();
    expect($dept->fresh()->name)->toBe('Computer Science');
});

it('admin can soft-delete a department with no offerings', function () {
    $dept = Department::create(['name' => 'CS', 'code' => 'CS']);

    $response = $this->actingAs($this->admin)->delete("/admin/references/departments/{$dept->id}");

    $response->assertRedirect();
    expect($dept->fresh()->trashed())->toBeTrue();
});

it('refuses to delete a department that still has offerings', function () {
    $dept = Department::create(['name' => 'CS', 'code' => 'CS']);
    ProgramOffering::create([
        'department_id' => $dept->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);

    $response = $this->actingAs($this->admin)->delete("/admin/references/departments/{$dept->id}");

    $response->assertRedirect();
    expect($dept->fresh()->trashed())->toBeFalse();
});

it('blocks recreating a department whose code/name still exists as a soft-deleted row', function () {
    $first = Department::create(['name' => 'CS', 'code' => 'CS']);
    $first->delete();

    $response = $this->actingAs($this->admin)->post('/admin/references/departments', [
        'name' => 'CS',
        'code' => 'CS',
    ]);

    $response->assertSessionHasErrors(['name', 'code']);
    expect(Department::query()->whereNull('deleted_at')->count())->toBe(0);
});
