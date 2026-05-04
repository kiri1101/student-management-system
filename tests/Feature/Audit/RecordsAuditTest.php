<?php

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;

it('writes a Created audit log with an attribute snapshot when a model is created', function () {
    $role = Role::create(['name' => RoleName::Student->value]);

    $log = AuditLog::query()
        ->where('subject_type', $role->getMorphClass())
        ->where('subject_id', $role->id)
        ->where('action', AuditAction::Created->value)
        ->sole();

    expect($log->changes)->toHaveKey('attributes')
        ->and($log->changes['attributes']['name'])->toBe(RoleName::Student->value);
});

it('writes an Updated audit log with a before/after diff', function () {
    $role = Role::create(['name' => RoleName::Student->value]);

    $role->name = RoleName::Lecturer->value;
    $role->save();

    $log = AuditLog::query()
        ->where('subject_type', $role->getMorphClass())
        ->where('subject_id', $role->id)
        ->where('action', AuditAction::Updated->value)
        ->sole();

    expect($log->changes)->toBe([
        'before' => ['name' => RoleName::Student->value],
        'after' => ['name' => RoleName::Lecturer->value],
    ]);
});

it('writes no Updated audit log when only timestamps change', function () {
    $role = Role::create(['name' => RoleName::Student->value]);

    $role->touch();

    expect(AuditLog::query()
        ->where('subject_type', $role->getMorphClass())
        ->where('subject_id', $role->id)
        ->where('action', AuditAction::Updated->value)
        ->count())->toBe(0);
});

it('writes a Deleted audit log when the model is soft-deleted', function () {
    $role = Role::create(['name' => RoleName::Student->value]);

    $role->delete();

    $log = AuditLog::query()
        ->where('subject_type', $role->getMorphClass())
        ->where('subject_id', $role->id)
        ->where('action', AuditAction::Deleted->value)
        ->sole();

    expect($log->changes['attributes']['name'])->toBe(RoleName::Student->value);
});

it('writes a Restored audit log when a soft-deleted model is restored', function () {
    $role = Role::create(['name' => RoleName::Student->value]);
    $role->delete();
    $role->restore();

    $log = AuditLog::query()
        ->where('subject_type', $role->getMorphClass())
        ->where('subject_id', $role->id)
        ->where('action', AuditAction::Restored->value)
        ->sole();

    expect($log->changes['attributes']['name'])->toBe(RoleName::Student->value);
});

it('attributes the audit log to the currently authenticated user', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $role = Role::create(['name' => RoleName::Student->value]);

    $log = AuditLog::query()
        ->where('subject_type', $role->getMorphClass())
        ->where('subject_id', $role->id)
        ->where('action', AuditAction::Created->value)
        ->sole();

    expect($log->user_id)->toBe($actor->id);
});

it('strips sensitive attributes from the snapshot', function () {
    $role = Role::create(['name' => RoleName::Student->value]);

    $log = AuditLog::query()
        ->where('subject_type', $role->getMorphClass())
        ->where('subject_id', $role->id)
        ->where('action', AuditAction::Created->value)
        ->sole();

    expect($log->changes['attributes'])->not->toHaveKey('updated_at')
        ->and($log->changes['attributes'])->not->toHaveKey('created_at')
        ->and($log->changes['attributes'])->not->toHaveKey('deleted_at');
});
