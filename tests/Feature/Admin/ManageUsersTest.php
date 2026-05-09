<?php

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Mail\UserInvitationMail;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\LecturerProfile;
use App\Models\SaoProfile;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->admin = userWithRole(RoleName::Admin);
    $this->withoutVite();
});

it('lists users with role + status + search filters', function () {
    $lecturer = User::factory()->create(['name' => 'Ada Lecturer', 'email' => 'ada@example.com']);
    $lecturer->assignRole(RoleName::Lecturer);

    $accountant = User::factory()->create(['name' => 'Bob Accountant', 'email' => 'bob@example.com']);
    $accountant->assignRole(RoleName::Accountant);

    $deactivated = User::factory()->create(['name' => 'Old User', 'email' => 'old@example.com']);
    $deactivated->assignRole(RoleName::Sao);
    $deactivated->delete();

    // Active default — excludes the trashed user and admin without filter; includes both staff
    $response = $this->actingAs($this->admin)->get('/admin/users');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/users/Index')
        ->has('users.data')
        ->where('users.data', fn ($users) => collect($users)->pluck('email')->doesntContain('old@example.com'))
    );

    // Role filter
    $response = $this->actingAs($this->admin)->get('/admin/users?roles[]=lecturer');
    $response->assertInertia(fn ($page) => $page
        ->where('users.data', fn ($users) => collect($users)->pluck('email')->all() === ['ada@example.com'])
    );

    // Search by name
    $response = $this->actingAs($this->admin)->get('/admin/users?search=Bob');
    $response->assertInertia(fn ($page) => $page
        ->where('users.data', fn ($users) => collect($users)->pluck('email')->all() === ['bob@example.com'])
    );

    // Trashed-only
    $response = $this->actingAs($this->admin)->get('/admin/users?status=trashed');
    $response->assertInertia(fn ($page) => $page
        ->where('users.data', fn ($users) => collect($users)->pluck('email')->all() === ['old@example.com'])
    );
});

it('soft-deletes and then restores a user without losing role + profile', function () {
    $sao = User::factory()->create(['email' => 'sao@example.com']);
    $sao->assignRole(RoleName::Sao);
    SaoProfile::create(['user_id' => $sao->id, 'scope' => 'Admissions']);

    $this->actingAs($this->admin)->delete("/admin/users/{$sao->id}")->assertRedirect();
    expect($sao->fresh()->trashed())->toBeTrue();
    expect(SaoProfile::where('user_id', $sao->id)->exists())->toBeTrue();

    $this->actingAs($this->admin)->post("/admin/users/{$sao->id}/restore")->assertRedirect();
    $restored = $sao->fresh();
    expect($restored->trashed())->toBeFalse();
    expect($restored->hasRole(RoleName::Sao))->toBeTrue();
    expect($restored->saoProfile)->not->toBeNull();
});

it('forbids self-deactivation', function () {
    $response = $this->actingAs($this->admin)->delete("/admin/users/{$this->admin->id}");

    $response->assertRedirect();
    expect($this->admin->fresh()->trashed())->toBeFalse();
});

it('regenerates and resends the invitation, invalidating the prior token', function () {
    Mail::fake();
    $sao = User::factory()->create(['email' => 'invitee@example.com']);
    $sao->assignRole(RoleName::Sao);

    $firstToken = Password::broker()->createToken($sao);
    expect(Password::broker()->tokenExists($sao, $firstToken))->toBeTrue();

    $this->actingAs($this->admin)
        ->post("/admin/users/{$sao->id}/resend-invite")
        ->assertRedirect();

    expect(Password::broker()->tokenExists($sao, $firstToken))->toBeFalse();
    Mail::assertQueued(UserInvitationMail::class, fn (UserInvitationMail $mail): bool => $mail->user->is($sao));
});

it('changes a user role: revokes prior role + profile, attaches new role + profile', function () {
    $department = Department::firstOrCreate(['code' => 'CS'], ['name' => 'Computer Science']);
    $sao = User::factory()->create(['email' => 'transition@example.com']);
    $sao->assignRole(RoleName::Sao);
    SaoProfile::create(['user_id' => $sao->id, 'scope' => 'Admissions']);

    $response = $this->actingAs($this->admin)->patch("/admin/users/{$sao->id}/role", [
        'role' => RoleName::Lecturer->value,
        'profile' => ['department_id' => $department->id, 'specialization' => 'AI'],
    ]);

    $response->assertRedirect();

    $fresh = $sao->fresh();
    expect($fresh->hasRole(RoleName::Sao))->toBeFalse();
    expect($fresh->hasRole(RoleName::Lecturer))->toBeTrue();
    expect(SaoProfile::where('user_id', $sao->id)->whereNull('deleted_at')->exists())->toBeFalse();
    expect(LecturerProfile::where('user_id', $sao->id)->whereNull('deleted_at')->exists())->toBeTrue();

    expect(AuditLog::query()
        ->where('subject_id', $sao->id)
        ->where('action', AuditAction::RoleRevoked)
        ->exists())->toBeTrue();
    expect(AuditLog::query()
        ->where('subject_id', $sao->id)
        ->where('action', AuditAction::RoleAssigned)
        ->exists())->toBeTrue();
});

it('forbids self-role-change', function () {
    $response = $this->actingAs($this->admin)->patch("/admin/users/{$this->admin->id}/role", [
        'role' => RoleName::Lecturer->value,
        'profile' => ['department_id' => Department::firstOrCreate(['code' => 'CS'], ['name' => 'CS'])->id],
    ]);

    $response->assertRedirect();
    expect($this->admin->fresh()->hasRole(RoleName::Admin))->toBeTrue();
    expect($this->admin->fresh()->hasRole(RoleName::Lecturer))->toBeFalse();
});

it('updates a staff user name + profile fields without changing role', function () {
    $sao = User::factory()->create(['name' => 'Old Name', 'email' => 'edit@example.com']);
    $sao->assignRole(RoleName::Sao);
    SaoProfile::create(['user_id' => $sao->id, 'scope' => 'Admissions']);

    $response = $this->actingAs($this->admin)->patch("/admin/users/{$sao->id}", [
        'name' => 'New Name',
        'profile' => ['scope' => 'Records'],
    ]);

    $response->assertRedirect();
    expect($sao->fresh()->name)->toBe('New Name');
    expect($sao->fresh()->saoProfile->scope)->toBe('Records');
});

it('rewrites profile fields without audit churn when changeRole keeps the same role', function () {
    $department = Department::firstOrCreate(['code' => 'CS'], ['name' => 'CS']);
    $user = User::factory()->create(['email' => 'samerole@example.com']);
    $user->assignRole(RoleName::Lecturer);
    $existing = LecturerProfile::create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'specialization' => 'Old',
    ]);

    $this->actingAs($this->admin)->patch("/admin/users/{$user->id}/role", [
        'role' => RoleName::Lecturer->value,
        'profile' => ['department_id' => $department->id, 'specialization' => 'New'],
    ])->assertRedirect();

    $fresh = LecturerProfile::find($existing->id);
    expect($fresh)->not->toBeNull();
    expect($fresh->trashed())->toBeFalse();
    expect($fresh->specialization)->toBe('New');

    expect(AuditLog::query()
        ->where('subject_id', $user->id)
        ->whereIn('action', [AuditAction::RoleRevoked, AuditAction::RoleAssigned])
        ->exists())->toBeFalse();
    expect(AuditLog::query()
        ->where('subject_type', LecturerProfile::class)
        ->where('subject_id', $existing->id)
        ->whereIn('action', [AuditAction::Deleted, AuditAction::Restored])
        ->exists())->toBeFalse();
});

it('reuses a previously soft-deleted profile row when re-attaching the same role', function () {
    $department = Department::firstOrCreate(['code' => 'CS'], ['name' => 'CS']);
    $user = User::factory()->create(['email' => 'roundtrip@example.com']);
    $user->assignRole(RoleName::Lecturer);
    $existing = LecturerProfile::create([
        'user_id' => $user->id,
        'department_id' => $department->id,
        'specialization' => 'Old',
    ]);
    $existing->delete();

    $this->actingAs($this->admin)->patch("/admin/users/{$user->id}/role", [
        'role' => RoleName::Lecturer->value,
        'profile' => ['department_id' => $department->id, 'specialization' => 'New'],
    ])->assertRedirect();

    $rows = LecturerProfile::withTrashed()->where('user_id', $user->id)->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first()->id)->toBe($existing->id);
    expect($rows->first()->trashed())->toBeFalse();
    expect($rows->first()->specialization)->toBe('New');
});
