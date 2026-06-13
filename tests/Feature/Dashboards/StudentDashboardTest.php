<?php

use App\Enums\ApplicationStatus;
use App\Enums\RoleName;
use App\Models\AccountantProfile;
use App\Models\Application;
use App\Models\LecturerProfile;
use App\Models\StudentProfile;

beforeEach(function () {
    $this->withoutVite();
});

it('shows the enrollment summary and application history to a student', function () {
    $application = Application::factory()->state([
        'status' => ApplicationStatus::Admitted,
        'submitted_at' => now()->subWeek(),
        'decided_at' => now(),
    ])->create();

    $student = $application->applicant;
    $student->assignRole(RoleName::Student);

    StudentProfile::factory()->create([
        'user_id' => $student->id,
        'matricule' => 'stm-2026-0001',
        'program_offering_id' => $application->program_offering_id,
        'level' => $application->level,
    ]);

    $this->actingAs($student)->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboards/Student')
            ->where('profile.matricule', 'stm-2026-0001')
            ->where('profile.level', $application->level)
            ->has('profile.program_offering.department')
            ->has('applications', 1)
            ->where('applications.0.status', 'admitted'));
});

it('renders a null profile gracefully when no enrollment record exists', function () {
    $student = userWithRole(RoleName::Student);

    $this->actingAs($student)->get(route('student.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboards/Student')
            ->where('profile', null)
            ->has('applications', 0));
});

it('shows the lecturer their profile card', function () {
    $lecturer = userWithRole(RoleName::Lecturer);
    LecturerProfile::factory()->create([
        'user_id' => $lecturer->id,
        'specialization' => 'Distributed systems',
    ]);

    $this->actingAs($lecturer)->get(route('lecturer.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboards/Lecturer')
            ->where('profile.specialization', 'Distributed systems')
            ->has('profile.department'));
});

it('shows the accountant their profile card', function () {
    $accountant = userWithRole(RoleName::Accountant);
    AccountantProfile::factory()->create([
        'user_id' => $accountant->id,
        'bank_desk' => 'UBA',
    ]);

    $this->actingAs($accountant)->get(route('accountant.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboards/Accountant')
            ->where('profile.bank_desk', 'UBA'));
});
