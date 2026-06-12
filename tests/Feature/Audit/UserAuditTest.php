<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function userAuditRows(User $user, AuditAction $action)
{
    return AuditLog::query()
        ->where('subject_type', $user->getMorphClass())
        ->where('subject_id', $user->id)
        ->where('action', $action->value);
}

it('writes exactly one Created row per user without sensitive keys', function () {
    $user = User::factory()->create();

    $row = userAuditRows($user, AuditAction::Created)->sole();

    expect($row->changes['attributes'])->toHaveKeys(['name', 'email'])
        ->and($row->changes['attributes'])->not->toHaveKey('password')
        ->and($row->changes['attributes'])->not->toHaveKey('remember_token');
});

it('audits name and email changes with before/after values', function () {
    $user = User::factory()->create(['name' => 'Before Name']);

    $user->update(['name' => 'After Name']);

    $row = userAuditRows($user, AuditAction::Updated)->sole();

    expect($row->changes)->toBe([
        'before' => ['name' => 'Before Name'],
        'after' => ['name' => 'After Name'],
    ]);
});

it('audits password changes with the value redacted', function () {
    $user = User::factory()->create();

    $user->forceFill(['password' => Hash::make('a-new-secret-password')])->save();

    $row = userAuditRows($user, AuditAction::Updated)->sole();

    expect($row->changes)->toBe([
        'before' => ['password' => '[redacted]'],
        'after' => ['password' => '[redacted]'],
    ]);
});

it('audits deactivation and restoration of a user', function () {
    $user = User::factory()->create();

    $user->delete();
    $user->restore();

    expect(userAuditRows($user, AuditAction::Deleted)->count())->toBe(1)
        ->and(userAuditRows($user, AuditAction::Restored)->count())->toBe(1);
});
