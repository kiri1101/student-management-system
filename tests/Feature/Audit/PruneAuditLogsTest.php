<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;

it('prunes audit rows older than the default retention horizon and keeps recent ones', function () {
    $stale = AuditLog::create([
        'action' => AuditAction::LoggedIn,
        'occurred_at' => now()->subDays(AuditLog::RETENTION_DAYS + 5),
    ]);
    $recent = AuditLog::create([
        'action' => AuditAction::LoggedIn,
        'occurred_at' => now()->subDays(10),
    ]);

    $this->artisan('audit:prune')
        ->expectsOutputToContain('Pruned 1 audit log record')
        ->assertSuccessful();

    expect(AuditLog::whereKey($stale->getKey())->exists())->toBeFalse()
        ->and(AuditLog::whereKey($recent->getKey())->exists())->toBeTrue();
});

it('respects a custom --days retention horizon', function () {
    $row = AuditLog::create([
        'action' => AuditAction::LoggedIn,
        'occurred_at' => now()->subDays(40),
    ]);

    $this->artisan('audit:prune', ['--days' => 30])->assertSuccessful();

    expect(AuditLog::whereKey($row->getKey())->exists())->toBeFalse();
});

it('rejects a retention horizon below one day', function () {
    $row = AuditLog::create([
        'action' => AuditAction::LoggedIn,
        'occurred_at' => now()->subDays(AuditLog::RETENTION_DAYS + 5),
    ]);

    $this->artisan('audit:prune', ['--days' => 0])->assertFailed();

    // Nothing was deleted because the command aborted on the invalid horizon.
    expect(AuditLog::whereKey($row->getKey())->exists())->toBeTrue();
});
