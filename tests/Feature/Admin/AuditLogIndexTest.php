<?php

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;

it('returns paginated audit logs newest first by default', function () {
    $admin = userWithRole(RoleName::Admin);

    AuditLog::record(
        AuditAction::LoggedIn,
        $admin,
        userId: $admin->id,
    );

    $department = Department::create(['name' => 'CS', 'code' => 'CS']);
    $department->update(['name' => 'Computer Science']);

    $response = $this->actingAs($admin)->getJson(route('admin.audit-logs.index'));

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'action', 'subject_type', 'subject_id',
                    'changes', 'context', 'occurred_at', 'user',
                ],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            'options' => ['actions', 'subject_types'],
        ]);

    $actions = collect($response->json('data'))->pluck('action')->all();

    expect($actions)->toContain(AuditAction::LoggedIn->value)
        ->and($actions)->toContain(AuditAction::Created->value)
        ->and($actions)->toContain(AuditAction::Updated->value);
});

it('filters by user_id', function () {
    $admin = userWithRole(RoleName::Admin);
    $other = User::factory()->create();

    AuditLog::record(AuditAction::LoggedIn, $admin, userId: $admin->id);
    AuditLog::record(AuditAction::LoggedIn, $other, userId: $other->id);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.audit-logs.index', ['user_id' => $other->id]));

    $response->assertOk();

    $userIds = collect($response->json('data'))
        ->pluck('user.id')
        ->unique()
        ->values()
        ->all();

    expect($userIds)->toBe([$other->id]);
});

it('filters by involving_user_id (actor or affected user account)', function () {
    $admin = userWithRole(RoleName::Admin);
    $target = User::factory()->create();
    $other = User::factory()->create();

    // Target as actor.
    AuditLog::record(AuditAction::LoggedIn, $target, userId: $target->id);
    // Target as affected subject, acted on by the admin.
    AuditLog::record(AuditAction::Updated, $target, userId: $admin->id);
    // Unrelated: another user as both actor and subject.
    AuditLog::record(AuditAction::LoggedIn, $other, userId: $other->id);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.audit-logs.index', ['involving_user_id' => $target->id]));

    $response->assertOk();

    $rows = collect($response->json('data'));

    // Every returned row must involve the target — as actor or as the affected
    // User account. This also proves $other's rows are excluded.
    foreach ($rows as $row) {
        $involves = ($row['user'] !== null && $row['user']['id'] === $target->id)
            || ($row['subject_type'] === 'User' && $row['subject_id'] === $target->id);

        expect($involves)->toBeTrue();
    }

    // Both explicitly-recorded entries are present (actor-only and subject-only).
    expect($rows->pluck('action')->all())
        ->toContain(AuditAction::LoggedIn->value)
        ->toContain(AuditAction::Updated->value);
});

it('filters by actions[]', function () {
    $admin = userWithRole(RoleName::Admin);

    AuditLog::record(AuditAction::LoggedIn, $admin, userId: $admin->id);
    AuditLog::record(AuditAction::LoginFailed, null, userId: null);
    AuditLog::record(AuditAction::LoggedOut, $admin, userId: $admin->id);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.audit-logs.index', [
            'actions' => [AuditAction::LoginFailed->value, AuditAction::LoggedOut->value],
        ]));

    $response->assertOk();

    $returned = collect($response->json('data'))->pluck('action')->all();

    expect($returned)->not->toContain(AuditAction::LoggedIn->value)
        ->and($returned)->toContain(AuditAction::LoginFailed->value)
        ->and($returned)->toContain(AuditAction::LoggedOut->value);
});

it('filters by subject_types[] using short class names', function () {
    $admin = userWithRole(RoleName::Admin);
    Department::create(['name' => 'CS', 'code' => 'CS']);
    Application::factory()->submitted()->create();

    $response = $this->actingAs($admin)
        ->getJson(route('admin.audit-logs.index', [
            'subject_types' => ['Department'],
        ]));

    $response->assertOk();

    $types = collect($response->json('data'))
        ->pluck('subject_type')
        ->unique()
        ->values()
        ->all();

    expect($types)->toBe(['Department']);
});

it('filters by from/to date range', function () {
    $admin = userWithRole(RoleName::Admin);
    $other = User::factory()->create();

    AuditLog::create([
        'user_id' => $other->id,
        'action' => AuditAction::LoggedIn,
        'context' => null,
        'occurred_at' => now()->subDays(10),
    ]);
    AuditLog::create([
        'user_id' => $other->id,
        'action' => AuditAction::LoggedIn,
        'context' => null,
        'occurred_at' => now()->subDays(2),
    ]);

    $from = now()->subDays(5)->toDateString();
    $to = now()->toDateString();

    $response = $this->actingAs($admin)
        ->getJson(route('admin.audit-logs.index', [
            'from' => $from,
            'to' => $to,
            'user_id' => $other->id,
        ]));

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
});

it('paginates with the rows query parameter', function () {
    $admin = userWithRole(RoleName::Admin);

    foreach (range(1, 12) as $i) {
        AuditLog::record(AuditAction::LoggedIn, $admin, userId: $admin->id);
    }

    $response = $this->actingAs($admin)
        ->getJson(route('admin.audit-logs.index', ['rows' => 5]));

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 5)
        ->assertJsonPath('meta.current_page', 1);

    expect(count($response->json('data')))->toBe(5);
});

it('exposes a value for every AuditAction case in options.actions', function () {
    $admin = userWithRole(RoleName::Admin);

    $response = $this->actingAs($admin)->getJson(route('admin.audit-logs.index'));

    $response->assertOk();

    $actionValues = collect($response->json('options.actions'))->pluck('value')->all();

    expect($actionValues)->toEqualCanonicalizing(array_column(AuditAction::cases(), 'value'));
});

it('rejects unknown subject_type filters with 422', function () {
    $admin = userWithRole(RoleName::Admin);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.audit-logs.index', ['subject_types' => ['NotARealClass']]));

    $response->assertStatus(422)->assertJsonValidationErrors(['subject_types.0']);
});

it('rejects an invalid date range with 422', function () {
    $admin = userWithRole(RoleName::Admin);

    $response = $this->actingAs($admin)
        ->getJson(route('admin.audit-logs.index', [
            'from' => '2026-05-10',
            'to' => '2026-05-01',
        ]));

    $response->assertStatus(422)->assertJsonValidationErrors(['to']);
});
