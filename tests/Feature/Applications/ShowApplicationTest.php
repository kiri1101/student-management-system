<?php

use App\Enums\DegreeProgram;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\ProgramOffering;
use App\Models\User;
use Database\Seeders\DocumentTypesSeeder;

beforeEach(function (): void {
    $this->seed(DocumentTypesSeeder::class);

    $this->department = Department::firstOrCreate(
        ['code' => 'CS'],
        ['name' => 'Computer Science'],
    );

    $this->offering = ProgramOffering::firstOrCreate(
        ['department_id' => $this->department->id, 'degree_program' => DegreeProgram::Bachelors->value],
        ['min_level' => 1, 'max_level' => 4],
    );
});

it('renders the show page for the application owner', function () {
    $user = User::factory()->create();
    $application = Application::factory()
        ->for($user, 'applicant')
        ->for($this->offering, 'programOffering')
        ->submitted()
        ->create();

    $nidId = DocumentType::where('code', 'NID')->value('id');
    ApplicationDocument::factory()->create([
        'application_id' => $application->id,
        'document_type_id' => $nidId,
        'original_filename' => 'nid.pdf',
    ]);

    $response = $this->actingAs($user)->get(route('application.show', $application));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('applicant/applications/Show')
        ->where('application.id', $application->id)
        ->has('application.documents', 1)
        ->where('application.documents.0.document_type.code', 'NID'));
});

it('blocks another user from viewing an application they do not own', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $application = Application::factory()
        ->for($alice, 'applicant')
        ->for($this->offering, 'programOffering')
        ->submitted()
        ->create();

    $response = $this->actingAs($bob)->get(route('application.show', $application));

    $response->assertForbidden();
});

it('blocks guests from the show page', function () {
    $alice = User::factory()->create();
    $application = Application::factory()
        ->for($alice, 'applicant')
        ->for($this->offering, 'programOffering')
        ->submitted()
        ->create();

    $response = $this->get(route('application.show', $application));

    $response->assertRedirect(route('login'));
});
