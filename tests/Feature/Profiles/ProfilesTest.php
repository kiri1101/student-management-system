<?php

use App\Enums\AuditAction;
use App\Enums\StudentStatus;
use App\Models\AccountantProfile;
use App\Models\AuditLog;
use App\Models\LecturerProfile;
use App\Models\SaoProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\QueryException;

it('exposes a hasOne studentProfile relationship', function () {
    $user = User::factory()->create();

    expect($user->studentProfile)->toBeNull();

    $profile = StudentProfile::factory()->create(['user_id' => $user->id]);

    expect($user->fresh()->studentProfile->id)->toBe($profile->id);
});

it('exposes a hasOne lecturerProfile relationship', function () {
    $user = User::factory()->create();

    expect($user->lecturerProfile)->toBeNull();

    $profile = LecturerProfile::factory()->create(['user_id' => $user->id]);

    expect($user->fresh()->lecturerProfile->id)->toBe($profile->id);
});

it('exposes a hasOne accountantProfile relationship', function () {
    $user = User::factory()->create();

    expect($user->accountantProfile)->toBeNull();

    $profile = AccountantProfile::factory()->create(['user_id' => $user->id]);

    expect($user->fresh()->accountantProfile->id)->toBe($profile->id);
});

it('exposes a hasOne saoProfile relationship', function () {
    $user = User::factory()->create();

    expect($user->saoProfile)->toBeNull();

    $profile = SaoProfile::factory()->create(['user_id' => $user->id]);

    expect($user->fresh()->saoProfile->id)->toBe($profile->id);
});

it('casts student status to the StudentStatus enum', function () {
    $profile = StudentProfile::factory()->create();

    expect($profile->status)->toBe(StudentStatus::Active);
});

it('writes a Created audit log for each profile model', function () {
    $student = StudentProfile::factory()->create();
    $lecturer = LecturerProfile::factory()->create();
    $accountant = AccountantProfile::factory()->create();
    $sao = SaoProfile::factory()->create();

    foreach ([$student, $lecturer, $accountant, $sao] as $profile) {
        $log = AuditLog::query()
            ->where('subject_type', $profile->getMorphClass())
            ->where('subject_id', $profile->id)
            ->where('action', AuditAction::Created->value)
            ->sole();

        expect($log->changes)->toHaveKey('attributes');
    }
});

it('blocks hard-deleting a user that still has a student profile', function () {
    $user = User::factory()->create();
    StudentProfile::factory()->create(['user_id' => $user->id]);

    expect(fn () => $user->forceDelete())->toThrow(QueryException::class);
});

it('blocks hard-deleting a user that still has a lecturer profile', function () {
    $user = User::factory()->create();
    LecturerProfile::factory()->create(['user_id' => $user->id]);

    expect(fn () => $user->forceDelete())->toThrow(QueryException::class);
});

it('blocks hard-deleting a user that still has an accountant profile', function () {
    $user = User::factory()->create();
    AccountantProfile::factory()->create(['user_id' => $user->id]);

    expect(fn () => $user->forceDelete())->toThrow(QueryException::class);
});

it('blocks hard-deleting a user that still has a sao profile', function () {
    $user = User::factory()->create();
    SaoProfile::factory()->create(['user_id' => $user->id]);

    expect(fn () => $user->forceDelete())->toThrow(QueryException::class);
});

it('enforces a unique matricule across student profiles', function () {
    StudentProfile::factory()->create(['matricule' => 'STM-DUP-1']);

    expect(fn () => StudentProfile::factory()->create(['matricule' => 'STM-DUP-1']))
        ->toThrow(QueryException::class);
});

it('enforces a unique user_id across each profile table', function () {
    $user = User::factory()->create();
    StudentProfile::factory()->create(['user_id' => $user->id]);

    expect(fn () => StudentProfile::factory()->create(['user_id' => $user->id]))
        ->toThrow(QueryException::class);
});
