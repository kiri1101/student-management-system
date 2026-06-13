<?php

use App\Enums\DegreeProgram;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\FeeInstallment;
use App\Models\FeeSchedule;
use App\Models\ProgramOffering;
use App\Models\User;

beforeEach(function () {
    $this->admin = userWithRole(RoleName::Admin);
    $this->department = Department::create(['name' => 'Computer Science', 'code' => 'CS']);
    $this->offering = ProgramOffering::create([
        'department_id' => $this->department->id,
        'degree_program' => DegreeProgram::Bachelors->value,
        'min_level' => 1,
        'max_level' => 4,
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function feePayload(ProgramOffering $offering, array $overrides = []): array
{
    return array_merge([
        'program_offering_id' => $offering->id,
        'level' => 1,
        'academic_year' => '2026',
        'total_xaf' => 500_000,
        'installments' => [
            ['sequence' => 1, 'label' => 'First', 'amount_xaf' => 300_000, 'due_date' => '2026-03-01'],
            ['sequence' => 2, 'label' => 'Second', 'amount_xaf' => 200_000, 'due_date' => '2026-07-01'],
        ],
    ], $overrides);
}

it('admin can list fee schedules with offering, department, and installments', function () {
    $schedule = FeeSchedule::create([
        'program_offering_id' => $this->offering->id,
        'level' => 1,
        'academic_year' => '2026',
        'total_xaf' => 500_000,
    ]);
    FeeInstallment::create([
        'fee_schedule_id' => $schedule->id,
        'sequence' => 1,
        'label' => 'First',
        'amount_xaf' => 500_000,
        'due_date' => '2026-03-01',
    ]);

    $response = $this->actingAs($this->admin)->get('/admin/fees');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/fees/Index')
        ->has('schedules', 1)
        ->has('schedules.0.installments', 1)
        ->has('offerings', 1));
});

it('admin can create a fee schedule with its installments', function () {
    $response = $this->actingAs($this->admin)->post('/admin/fees', feePayload($this->offering));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    expect(FeeSchedule::count())->toBe(1);
    expect(FeeInstallment::count())->toBe(2);
});

it('allows a schedule with no installments', function () {
    $response = $this->actingAs($this->admin)
        ->post('/admin/fees', feePayload($this->offering, ['installments' => []]));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    expect(FeeSchedule::count())->toBe(1);
    expect(FeeInstallment::count())->toBe(0);
});

it('rejects a duplicate (offering, level, year)', function () {
    FeeSchedule::create([
        'program_offering_id' => $this->offering->id,
        'level' => 1,
        'academic_year' => '2026',
        'total_xaf' => 500_000,
    ]);

    $response = $this->actingAs($this->admin)->post('/admin/fees', feePayload($this->offering));

    $response->assertSessionHasErrors('academic_year');
    expect(FeeSchedule::count())->toBe(1);
});

it('allows the same offering and level in a different year', function () {
    FeeSchedule::create([
        'program_offering_id' => $this->offering->id,
        'level' => 1,
        'academic_year' => '2025',
        'total_xaf' => 500_000,
    ]);

    $response = $this->actingAs($this->admin)->post('/admin/fees', feePayload($this->offering));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    expect(FeeSchedule::count())->toBe(2);
});

it('rejects installments whose sum exceeds the schedule total', function () {
    $response = $this->actingAs($this->admin)->post('/admin/fees', feePayload($this->offering, [
        'total_xaf' => 400_000,
    ]));

    $response->assertSessionHasErrors('installments');
    expect(FeeSchedule::count())->toBe(0);
});

it('rejects installments whose due dates do not increase with sequence', function () {
    $response = $this->actingAs($this->admin)->post('/admin/fees', feePayload($this->offering, [
        'installments' => [
            ['sequence' => 1, 'label' => 'First', 'amount_xaf' => 300_000, 'due_date' => '2026-07-01'],
            ['sequence' => 2, 'label' => 'Second', 'amount_xaf' => 200_000, 'due_date' => '2026-03-01'],
        ],
    ]));

    $response->assertSessionHasErrors('installments');
    expect(FeeSchedule::count())->toBe(0);
});

it('rejects duplicate installment sequences', function () {
    $response = $this->actingAs($this->admin)->post('/admin/fees', feePayload($this->offering, [
        'installments' => [
            ['sequence' => 1, 'label' => 'First', 'amount_xaf' => 250_000, 'due_date' => '2026-03-01'],
            ['sequence' => 1, 'label' => 'Second', 'amount_xaf' => 250_000, 'due_date' => '2026-07-01'],
        ],
    ]));

    $response->assertSessionHasErrors('installments.0.sequence');
});

it('rejects a level outside the offering range', function () {
    $response = $this->actingAs($this->admin)->post('/admin/fees', feePayload($this->offering, [
        'level' => 5,
    ]));

    $response->assertSessionHasErrors('level');
    expect(FeeSchedule::count())->toBe(0);
});

it('rejects a non-positive total', function () {
    $response = $this->actingAs($this->admin)->post('/admin/fees', feePayload($this->offering, [
        'total_xaf' => 0,
        'installments' => [],
    ]));

    $response->assertSessionHasErrors('total_xaf');
});

it('admin can update a schedule and replace its installments', function () {
    $this->actingAs($this->admin)->post('/admin/fees', feePayload($this->offering));
    $schedule = FeeSchedule::firstOrFail();

    $response = $this->actingAs($this->admin)->patch("/admin/fees/{$schedule->id}", feePayload($this->offering, [
        'total_xaf' => 600_000,
        'installments' => [
            ['sequence' => 1, 'label' => 'Lump sum', 'amount_xaf' => 600_000, 'due_date' => '2026-04-01'],
        ],
    ]));

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    expect($schedule->fresh()->total_xaf)->toBe(600_000);
    expect($schedule->installments()->count())->toBe(1);
    expect($schedule->installments()->first()->label)->toBe('Lump sum');
});

it('admin can soft-delete a schedule', function () {
    $schedule = FeeSchedule::create([
        'program_offering_id' => $this->offering->id,
        'level' => 1,
        'academic_year' => '2026',
        'total_xaf' => 500_000,
    ]);

    $response = $this->actingAs($this->admin)->delete("/admin/fees/{$schedule->id}");

    $response->assertRedirect();
    expect($schedule->fresh()->trashed())->toBeTrue();
});

it('refuses to restore a schedule while its offering is trashed', function () {
    $schedule = FeeSchedule::create([
        'program_offering_id' => $this->offering->id,
        'level' => 1,
        'academic_year' => '2026',
        'total_xaf' => 500_000,
    ]);
    $schedule->delete();
    $this->offering->delete();

    $response = $this->actingAs($this->admin)->post("/admin/fees/{$schedule->id}/restore");

    $response->assertRedirect();
    expect($schedule->fresh()->trashed())->toBeTrue();
});

it('restores a schedule when its offering is present', function () {
    $schedule = FeeSchedule::create([
        'program_offering_id' => $this->offering->id,
        'level' => 1,
        'academic_year' => '2026',
        'total_xaf' => 500_000,
    ]);
    $schedule->delete();

    $response = $this->actingAs($this->admin)->post("/admin/fees/{$schedule->id}/restore");

    $response->assertRedirect();
    expect($schedule->fresh()->trashed())->toBeFalse();
});

it('blocks recreating a (offering, level, year) while a soft-deleted one exists', function () {
    $schedule = FeeSchedule::create([
        'program_offering_id' => $this->offering->id,
        'level' => 1,
        'academic_year' => '2026',
        'total_xaf' => 500_000,
    ]);
    $schedule->delete();

    $response = $this->actingAs($this->admin)->post('/admin/fees', feePayload($this->offering));

    $response->assertSessionHasErrors('academic_year');
    expect(FeeSchedule::query()->whereNull('deleted_at')->count())->toBe(0);
});

it('forbids non-admin roles from the fee endpoints', function (RoleName $role) {
    $user = userWithRole($role);

    $this->actingAs($user)->get('/admin/fees')->assertForbidden();
    $this->actingAs($user)->post('/admin/fees', feePayload($this->offering))->assertForbidden();
})->with(function () {
    foreach (RoleName::cases() as $role) {
        if ($role !== RoleName::Admin) {
            yield $role->value => [$role];
        }
    }
});

it('redirects guests to login', function () {
    $this->get('/admin/fees')->assertRedirect(route('login', absolute: false));
});

it('forbids roleless verified users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/fees')->assertForbidden();
});
