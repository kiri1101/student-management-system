<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;

test('record persists an audit row with the supplied action, subject and changes', function () {
    $user = User::factory()->create();

    $log = AuditLog::record(
        AuditAction::StatusChanged,
        $user,
        ['before' => ['status' => 'pending'], 'after' => ['status' => 'admitted']],
        ['note' => 'manual override'],
        $user->id,
    );

    expect($log->action)->toBe(AuditAction::StatusChanged)
        ->and($log->subject_type)->toBe($user->getMorphClass())
        ->and($log->subject_id)->toBe($user->id)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->changes)->toBe(['before' => ['status' => 'pending'], 'after' => ['status' => 'admitted']])
        ->and($log->context['note'] ?? null)->toBe('manual override')
        ->and($log->occurred_at)->not->toBeNull();
});

test('record falls back to the authenticated user when no userId is provided', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $log = AuditLog::record(AuditAction::LoggedIn, $user);

    expect($log->user_id)->toBe($user->id);
});

test('record accepts a null subject for system events', function () {
    $log = AuditLog::record(AuditAction::LoginFailed, null, null, [], null);

    expect($log->subject_type)->toBeNull()
        ->and($log->subject_id)->toBeNull()
        ->and($log->user_id)->toBeNull();
});

test('buildContext fills in ip from the current request', function () {
    $context = AuditLog::buildContext();

    expect($context)->toHaveKey('ip')
        ->and($context['ip'])->toBe('127.0.0.1');
});

test('buildContext lets caller-supplied keys win on conflict', function () {
    $context = AuditLog::buildContext(['ip' => 'override']);

    expect($context['ip'])->toBe('override');
});

test('updating an audit log throws', function () {
    $user = User::factory()->create();
    $log = AuditLog::record(AuditAction::LoggedIn, $user);

    expect(fn () => $log->update(['action' => AuditAction::Updated->value]))
        ->toThrow(RuntimeException::class, 'immutable');
});

test('saving a mutated audit log throws', function () {
    $user = User::factory()->create();
    $log = AuditLog::record(AuditAction::LoggedIn, $user);

    $log->action = AuditAction::Updated;

    expect(fn () => $log->save())
        ->toThrow(RuntimeException::class, 'immutable');
});

test('deleting an audit log throws', function () {
    $user = User::factory()->create();
    $log = AuditLog::record(AuditAction::LoggedIn, $user);

    expect(fn () => $log->delete())
        ->toThrow(RuntimeException::class, 'immutable');
});

test('subject relation resolves back to the morph target', function () {
    $user = User::factory()->create();
    $log = AuditLog::record(AuditAction::LoggedIn, $user);

    expect($log->subject)->not->toBeNull()
        ->and($log->subject->is($user))->toBeTrue();
});
