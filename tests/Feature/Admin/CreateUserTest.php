<?php

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Mail\UserInvitationMail;
use App\Models\AccountantProfile;
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

dataset('staff_roles', function () {
    yield 'lecturer' => [
        RoleName::Lecturer,
        fn () => ['department_id' => Department::firstOrCreate(['code' => 'CS'], ['name' => 'Computer Science'])->id],
        LecturerProfile::class,
    ];
    yield 'accountant' => [
        RoleName::Accountant,
        fn () => ['bank_desk' => 'UBA', 'cashier_window' => 'Window 1'],
        AccountantProfile::class,
    ];
    yield 'sao' => [
        RoleName::Sao,
        fn () => ['scope' => 'Admissions'],
        SaoProfile::class,
    ];
    yield 'admin' => [RoleName::Admin, fn () => [], null];
});

it('creates a staff user with role + profile + invitation in one transaction', function (
    RoleName $role,
    Closure $profileBuilder,
    ?string $profileModel,
) {
    Mail::fake();

    $payload = [
        'name' => 'New Staff',
        'email' => 'new.staff@example.com',
        'role' => $role->value,
        'profile' => $profileBuilder(),
    ];

    $response = $this->actingAs($this->admin)->post('/admin/users', $payload);

    $response->assertRedirect(route('admin.users.index'));

    $user = User::query()->where('email', 'new.staff@example.com')->firstOrFail();

    expect($user->email_verified_at)->not->toBeNull();
    expect($user->hasRole($role))->toBeTrue();

    if ($profileModel !== null) {
        expect($profileModel::where('user_id', $user->id)->exists())->toBeTrue();
    }

    Mail::assertQueued(UserInvitationMail::class, fn (UserInvitationMail $mail): bool => $mail->user->is($user));
})->with('staff_roles');

it('forbids non-admin users from creating accounts', function () {
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post('/admin/users', [
        'name' => 'New Staff',
        'email' => 'denied@example.com',
        'role' => RoleName::Lecturer->value,
    ]);

    $response->assertForbidden();
    expect(User::query()->where('email', 'denied@example.com')->exists())->toBeFalse();
});

it('rejects creating Applicant or Student roles via the admin flow', function () {
    foreach ([RoleName::Applicant, RoleName::Student] as $role) {
        $response = $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'X',
            'email' => "x-{$role->value}@example.com",
            'role' => $role->value,
        ]);

        $response->assertSessionHasErrors('role');
    }
});

it('rejects an email that matches a soft-deleted user with a restore hint', function () {
    Mail::fake();
    $existing = User::factory()->create(['email' => 'returning@example.com']);
    $existing->delete();

    $response = $this->actingAs($this->admin)->post('/admin/users', [
        'name' => 'New Person',
        'email' => 'returning@example.com',
        'role' => RoleName::Sao->value,
        'profile' => ['scope' => 'Admissions'],
    ]);

    $response->assertSessionHasErrors('email');
    expect(session('errors')->get('email')[0])->toContain('restore');
    Mail::assertNothingQueued();
});

it('rolls back user creation if the profile insert fails', function () {
    Mail::fake();

    $response = $this->actingAs($this->admin)->post('/admin/users', [
        'name' => 'Half Lecturer',
        'email' => 'half.lecturer@example.com',
        'role' => RoleName::Lecturer->value,
        'profile' => ['department_id' => 99999],
    ]);

    $response->assertSessionHasErrors('profile.department_id');
    expect(User::query()->where('email', 'half.lecturer@example.com')->exists())->toBeFalse();
    Mail::assertNothingQueued();
});

it('queues an invitation containing a valid Fortify reset token', function () {
    Mail::fake();

    $this->actingAs($this->admin)->post('/admin/users', [
        'name' => 'Token Tester',
        'email' => 'token.tester@example.com',
        'role' => RoleName::Sao->value,
        'profile' => ['scope' => 'Admissions'],
    ]);

    $user = User::query()->where('email', 'token.tester@example.com')->firstOrFail();

    Mail::assertQueued(UserInvitationMail::class, function (UserInvitationMail $mail) use ($user): bool {
        $valid = Password::broker()->tokenExists($user, $mail->token);

        return $mail->user->is($user) && $valid;
    });
});

it('records Created (User) + RoleAssigned + Created (profile) audit entries', function () {
    Mail::fake();

    $this->actingAs($this->admin)->post('/admin/users', [
        'name' => 'Audited',
        'email' => 'audited@example.com',
        'role' => RoleName::Sao->value,
        'profile' => ['scope' => 'Admissions'],
    ]);

    $user = User::query()->where('email', 'audited@example.com')->firstOrFail();

    expect(AuditLog::query()
        ->where('subject_type', $user->getMorphClass())
        ->where('subject_id', $user->id)
        ->where('action', AuditAction::Created)
        ->exists())->toBeTrue();

    expect(AuditLog::query()
        ->where('subject_type', $user->getMorphClass())
        ->where('subject_id', $user->id)
        ->where('action', AuditAction::RoleAssigned)
        ->exists())->toBeTrue();

    expect(AuditLog::query()
        ->where('subject_type', SaoProfile::query()->where('user_id', $user->id)->firstOrFail()->getMorphClass())
        ->where('action', AuditAction::Created)
        ->exists())->toBeTrue();
});

it('renders the invitation mail with the human role label', function () {
    $user = User::factory()->create(['name' => 'Render Tester', 'email' => 'render@example.com']);
    $user->assignRole(RoleName::Sao);
    $token = Password::broker()->createToken($user);

    $rendered = (new UserInvitationMail($user, $token))->render();

    expect($rendered)->toContain('SAO');
    expect($rendered)->toContain('render@example.com');
    expect($rendered)->toContain('Set your password');
});

it('never persists or audits the random plaintext password', function () {
    Mail::fake();

    $this->actingAs($this->admin)->post('/admin/users', [
        'name' => 'Secret',
        'email' => 'secret@example.com',
        'role' => RoleName::Sao->value,
        'profile' => ['scope' => 'Admissions'],
    ]);

    $user = User::query()->where('email', 'secret@example.com')->firstOrFail();

    foreach (AuditLog::query()->where('subject_id', $user->id)->get() as $log) {
        $serialised = json_encode([$log->changes, $log->context]);
        expect($serialised)->not->toContain('password');
    }

    Mail::assertQueued(UserInvitationMail::class, function (UserInvitationMail $mail) use ($user): bool {
        $serialised = serialize($mail);

        return $mail->user->is($user)
            && ! str_contains($serialised, $user->password)
            && stripos($serialised, 'plaintext') === false;
    });
});
