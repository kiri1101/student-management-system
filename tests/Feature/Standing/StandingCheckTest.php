<?php

use App\Enums\RoleName;
use App\Models\FeeInstallment;
use App\Models\FeeSchedule;
use App\Models\StudentProfile;
use App\Models\TuitionDeferral;
use App\Models\User;
use Database\Seeders\RolesSeeder;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

function blockedStudent(): StudentProfile
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::Student);
    $year = (string) now()->year;

    $profile = StudentProfile::factory()->create([
        'user_id' => $user->id,
        'level' => 1,
        'academic_year' => $year,
    ]);

    $schedule = FeeSchedule::factory()->create([
        'program_offering_id' => $profile->program_offering_id,
        'level' => 1,
        'academic_year' => $year,
        'total_xaf' => 500000,
    ]);

    FeeInstallment::factory()->create([
        'fee_schedule_id' => $schedule->id,
        'sequence' => 1,
        'amount_xaf' => 300000,
        'due_date' => now()->subMonth()->toDateString(),
    ]);

    return $profile;
}

it('surfaces the standing and deferral list on the student payments page', function () {
    $profile = blockedStudent();
    TuitionDeferral::factory()->requested()->for($profile, 'studentProfile')->create([
        'academic_year' => $profile->academic_year,
    ]);

    $response = $this->actingAs($profile->user)->get(route('student.payments.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('student/payments/Index')
        ->where('standing.standing', 'blocked')
        ->where('standing.shortfall', 300000)
        ->has('deferrals', 1));
});

it('lets staff look up a student standing by matricule', function () {
    $profile = blockedStudent();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->get(route('standing.check', ['matricule' => $profile->matricule]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('staff/StandingCheck')
        ->where('result.found', true)
        ->where('result.standing.standing', 'blocked')
        ->where('result.student.matricule', $profile->matricule));
});

it('reports not found for an unknown matricule', function () {
    $accountant = userWithRole(RoleName::Accountant);

    $response = $this->actingAs($accountant)->get(route('standing.check', ['matricule' => 'stm-9999-0001']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('result.found', false));
});

it('loads the standing page with no query', function () {
    $admin = userWithRole(RoleName::Admin);

    $response = $this->actingAs($admin)->get(route('standing.check'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('staff/StandingCheck')
        ->where('result', null));
});

it('forbids non-staff roles from the standing check', function (RoleName $role) {
    $user = userWithRole($role);

    $this->actingAs($user)->get(route('standing.check'))->assertForbidden();
})->with([
    'student' => [RoleName::Student],
    'lecturer' => [RoleName::Lecturer],
    'applicant' => [RoleName::Applicant],
]);

it('redirects guests to login', function () {
    $this->get(route('standing.check'))->assertRedirect(route('login', absolute: false));
});
