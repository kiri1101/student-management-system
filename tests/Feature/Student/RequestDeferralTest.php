<?php

use App\Enums\DeferralStatus;
use App\Enums\RoleName;
use App\Models\StudentProfile;
use App\Models\TuitionDeferral;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

function deferralStudent(array $profileOverrides = []): StudentProfile
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::Student);

    return StudentProfile::factory()->create([
        'user_id' => $user->id,
        'academic_year' => '2026',
        ...$profileOverrides,
    ]);
}

it('lets a student submit a deferral request', function () {
    $profile = deferralStudent();

    $response = $this->actingAs($profile->user)->post(route('student.deferrals.store'), [
        'reason' => 'Awaiting my scholarship disbursement.',
        'requested_new_deadline' => now()->addMonth()->toDateString(),
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $deferral = TuitionDeferral::sole();
    expect($deferral->status)->toBe(DeferralStatus::Requested)
        ->and($deferral->student_profile_id)->toBe($profile->id)
        ->and($deferral->academic_year)->toBe('2026');
});

it('requires a reason', function () {
    $profile = deferralStudent();

    $this->actingAs($profile->user)
        ->post(route('student.deferrals.store'), ['reason' => ''])
        ->assertSessionHasErrors('reason');
});

it('rejects a desired deadline in the past', function () {
    $profile = deferralStudent();

    $this->actingAs($profile->user)->post(route('student.deferrals.store'), [
        'reason' => 'Need more time.',
        'requested_new_deadline' => now()->subDay()->toDateString(),
    ])->assertSessionHasErrors('requested_new_deadline');
});

it('refuses a second pending request for the same year', function () {
    $profile = deferralStudent();
    TuitionDeferral::factory()->requested()->for($profile, 'studentProfile')->create(['academic_year' => '2026']);

    $this->actingAs($profile->user)->post(route('student.deferrals.store'), [
        'reason' => 'Another request.',
    ])->assertSessionHasErrors('reason');

    expect(TuitionDeferral::count())->toBe(1);
});

it('allows a new request once the previous one was decided', function () {
    $profile = deferralStudent();
    TuitionDeferral::factory()->rejected()->for($profile, 'studentProfile')->create(['academic_year' => '2026']);

    $this->actingAs($profile->user)->post(route('student.deferrals.store'), [
        'reason' => 'Re-applying after rejection.',
    ])->assertSessionHasNoErrors();

    expect(TuitionDeferral::count())->toBe(2);
});

it('refuses a request from a user without a student enrollment', function () {
    $admin = userWithRole(RoleName::Admin);

    $this->actingAs($admin)->post(route('student.deferrals.store'), [
        'reason' => 'No enrollment.',
    ])->assertSessionHasErrors('reason');

    expect(TuitionDeferral::count())->toBe(0);
});

it('forbids non-student, non-admin roles', function () {
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('student.deferrals.store'), [
        'reason' => 'x',
    ])->assertForbidden();
});
