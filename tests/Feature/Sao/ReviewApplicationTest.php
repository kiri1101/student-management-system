<?php

use App\Enums\ApplicationStatus;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\DocumentType;
use App\Models\StudentProfile;
use App\Models\User;

it('renders the review screen with applicant + programme + documents', function () {
    $application = Application::factory()->submitted()->create();
    $nid = DocumentType::firstOrCreate(['code' => 'NID'], ['name' => 'National Identity']);
    $birth = DocumentType::firstOrCreate(['code' => 'BIRTH'], ['name' => 'Birth Certificate']);
    ApplicationDocument::factory()->create([
        'application_id' => $application->id,
        'document_type_id' => $nid->id,
    ]);
    ApplicationDocument::factory()->create([
        'application_id' => $application->id,
        'document_type_id' => $birth->id,
    ]);
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->get(route('sao.applications.show', $application));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sao/applications/Review')
            ->where('application.id', $application->id)
            ->where('application.status', 'submitted')
            ->where('application.is_terminal', false)
            ->has('application.documents', 2)
            ->where('application.applicant.id', $application->user_id)
            ->where('application.program_offering.id', $application->program_offering_id)
            ->where('priorHistory.profiles', [])
            ->where('priorHistory.applications', []));
});

it('surfaces a soft-deleted prior enrollment and prior decided application', function () {
    $applicant = User::factory()->create();

    $priorProfile = StudentProfile::factory()->create([
        'user_id' => $applicant->id,
        'matricule' => 'stm-2025-0042',
        'level' => 200,
        'academic_year' => '2024-2025',
        'status' => StudentStatus::Active,
    ]);
    $priorProfile->delete();

    $priorApplication = Application::factory()->state([
        'user_id' => $applicant->id,
        'status' => ApplicationStatus::Rejected,
        'submitted_at' => now()->subYear(),
        'decided_at' => now()->subYear()->addDay(),
        'decision_notes' => 'Missing documents.',
    ])->create();

    $current = Application::factory()->submitted()->state(['user_id' => $applicant->id])->create();

    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->get(route('sao.applications.show', $current));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('priorHistory.profiles', 1)
            ->where('priorHistory.profiles.0.id', $priorProfile->id)
            ->where('priorHistory.profiles.0.matricule', 'stm-2025-0042')
            ->whereNot('priorHistory.profiles.0.deleted_at', null)
            ->has('priorHistory.applications', 1)
            ->where('priorHistory.applications.0.id', $priorApplication->id)
            ->where('priorHistory.applications.0.status', 'rejected'));
});

it('marks terminal applications with is_terminal true', function () {
    $application = Application::factory()->state([
        'status' => ApplicationStatus::Admitted,
        'submitted_at' => now()->subDay(),
        'decided_at' => now(),
    ])->create();

    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->get(route('sao.applications.show', $application));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->where('application.is_terminal', true));
});
