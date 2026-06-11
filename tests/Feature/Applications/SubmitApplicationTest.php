<?php

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\DegreeProgram;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\LevelCredentialRequirement;
use App\Models\ProgramOffering;
use App\Models\User;
use Database\Seeders\DocumentTypesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake();
    $this->seed(DocumentTypesSeeder::class);

    $this->department = Department::firstOrCreate(
        ['code' => 'CS'],
        ['name' => 'Computer Science'],
    );

    $this->offering = ProgramOffering::firstOrCreate(
        ['department_id' => $this->department->id, 'degree_program' => DegreeProgram::Bachelors->value],
        ['min_level' => 1, 'max_level' => 4],
    );

    $gceAlId = DocumentType::where('code', 'GCE_AL')->value('id');
    LevelCredentialRequirement::firstOrCreate(
        ['program_offering_id' => $this->offering->id, 'level' => 1, 'document_type_id' => $gceAlId],
        ['required' => true],
    );
});

function applicationPayload(array $overrides = []): array
{
    return array_merge([
        'program_offering_id' => test()->offering->id,
        'level' => 1,
        'first_name' => 'Jordan',
        'last_name' => 'Tukue',
        'contact_email' => 'jordan@example.com',
        'phone' => '600000000',
        'date_of_birth' => '2000-01-15',
        'previous_institute' => 'Lycée Bilingue',
        'documents' => [
            'NID' => UploadedFile::fake()->create('nid.pdf', 100, 'application/pdf'),
            'BIRTH' => UploadedFile::fake()->create('birth.pdf', 100, 'application/pdf'),
            'GCE_AL' => UploadedFile::fake()->create('gce.pdf', 100, 'application/pdf'),
        ],
    ], $overrides);
}

it('rejects a second application while one is still open', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('application.store'), applicationPayload())
        ->assertSessionDoesntHaveErrors();

    $response = $this->actingAs($user)->post(route('application.store'), applicationPayload());

    $response->assertSessionHasErrors('program_offering_id');
    expect(Application::count())->toBe(1);
});

it('allows a new application once the previous one is decided', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('application.store'), applicationPayload())
        ->assertSessionDoesntHaveErrors();

    Application::sole()->update(['status' => ApplicationStatus::Rejected, 'decided_at' => now()]);

    $this->actingAs($user)->post(route('application.store'), applicationPayload())
        ->assertSessionDoesntHaveErrors();

    expect(Application::count())->toBe(2);
});

it('persists an application, its documents, and audit entries on submit', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('application.store'), applicationPayload());

    $response->assertRedirect(route('applicant.dashboard'));

    $application = Application::sole();
    expect($application->user_id)->toBe($user->id)
        ->and($application->status)->toBe(ApplicationStatus::Submitted)
        ->and($application->submitted_at)->not->toBeNull()
        ->and($application->documents)->toHaveCount(3);

    $applicationCreated = AuditLog::query()
        ->where('subject_type', $application->getMorphClass())
        ->where('subject_id', $application->id)
        ->where('action', AuditAction::Created->value)
        ->sole();
    expect($applicationCreated->user_id)->toBe($user->id);

    $documentCreatedCount = AuditLog::query()
        ->where('subject_type', $application->documents->first()->getMorphClass())
        ->where('action', AuditAction::Created->value)
        ->count();
    expect($documentCreatedCount)->toBe(3);
});

it('rejects a level outside the offering range', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('application.store'), applicationPayload([
        'level' => 5,
    ]));

    $response->assertSessionHasErrors('level');
    expect(Application::count())->toBe(0);
});

it('rejects a payload missing the always-required NID document', function () {
    $user = User::factory()->create();
    $payload = applicationPayload();
    unset($payload['documents']['NID']);

    $response = $this->actingAs($user)->post(route('application.store'), $payload);

    $response->assertSessionHasErrors('documents.NID');
    expect(Application::count())->toBe(0);
});

it('rejects a payload missing the level credential document', function () {
    $user = User::factory()->create();
    $payload = applicationPayload();
    unset($payload['documents']['GCE_AL']);

    $response = $this->actingAs($user)->post(route('application.store'), $payload);

    $response->assertSessionHasErrors('documents.GCE_AL');
    expect(Application::count())->toBe(0);
});

it('rejects an oversized upload', function () {
    $user = User::factory()->create();
    $payload = applicationPayload();
    $payload['documents']['NID'] = UploadedFile::fake()->create('huge.pdf', 9000, 'application/pdf');

    $response = $this->actingAs($user)->post(route('application.store'), $payload);

    $response->assertSessionHasErrors('documents.NID');
});

it('rejects an unsupported mime type', function () {
    $user = User::factory()->create();
    $payload = applicationPayload();
    $payload['documents']['NID'] = UploadedFile::fake()->create('nid.exe', 100, 'application/octet-stream');

    $response = $this->actingAs($user)->post(route('application.store'), $payload);

    $response->assertSessionHasErrors('documents.NID');
});

it('blocks guests', function () {
    $response = $this->post(route('application.store'), applicationPayload());

    $response->assertRedirect(route('login'));
    expect(Application::count())->toBe(0);
});

it('blocks unverified users', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->post(route('application.store'), applicationPayload());

    $response->assertRedirect(route('verification.notice'));
    expect(Application::count())->toBe(0);
});

it('auto-attaches the Applicant role on a roleless user first submit', function () {
    $user = User::factory()->create();
    expect($user->roles()->exists())->toBeFalse();

    $this->actingAs($user)->post(route('application.store'), applicationPayload());

    expect($user->fresh()->hasRole(RoleName::Applicant))->toBeTrue();

    $roleAssigned = AuditLog::query()
        ->where('action', AuditAction::RoleAssigned->value)
        ->where('subject_type', $user->getMorphClass())
        ->where('subject_id', $user->id)
        ->sole();

    expect($roleAssigned->changes)->toBe(['role' => RoleName::Applicant->value]);
});

it('does not change roles when the user already has one', function () {
    $user = userWithRole(RoleName::Student);

    $this->actingAs($user)->post(route('application.store'), applicationPayload());

    expect($user->fresh()->hasRole(RoleName::Student))->toBeTrue();
    expect($user->fresh()->hasRole(RoleName::Applicant))->toBeFalse();
    expect(AuditLog::query()->where('action', AuditAction::RoleAssigned->value)->exists())->toBeFalse();
});

it('keeps audit history queryable after a soft-deleted application', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('application.store'), applicationPayload());

    $application = Application::sole();
    $application->delete();

    $logs = AuditLog::query()
        ->where('subject_type', $application->getMorphClass())
        ->where('subject_id', $application->id)
        ->orderBy('id')
        ->get();

    expect($logs->pluck('action')->all())->toBe([
        AuditAction::Created,
        AuditAction::Deleted,
    ]);
    expect(Application::withTrashed()->find($application->id))->not->toBeNull();
});
