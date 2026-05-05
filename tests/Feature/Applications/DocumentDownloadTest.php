<?php

use App\Enums\RoleName;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');

    $this->applicant = User::factory()->create();
    $this->application = Application::factory()->submitted()->state([
        'user_id' => $this->applicant->id,
    ])->create();

    $file = UploadedFile::fake()->create('nid.pdf', 100, 'application/pdf');
    $path = $file->store('applications', 'local');

    $this->document = ApplicationDocument::factory()->create([
        'application_id' => $this->application->id,
        'file_path' => $path,
        'original_filename' => 'national-id.pdf',
        'mime_type' => 'application/pdf',
    ]);
});

it('lets the application owner download their own document', function () {
    $response = $this->actingAs($this->applicant)->get(route('application.documents.download', [
        'application' => $this->application->id,
        'document' => $this->document->id,
    ]));

    $response->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=national-id.pdf');
});

it('lets a SAO download any application document', function () {
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->get(route('application.documents.download', [
        'application' => $this->application->id,
        'document' => $this->document->id,
    ]));

    $response->assertOk();
});

it('lets an admin download any application document', function () {
    $admin = userWithRole(RoleName::Admin);

    $response = $this->actingAs($admin)->get(route('application.documents.download', [
        'application' => $this->application->id,
        'document' => $this->document->id,
    ]));

    $response->assertOk();
});

it('forbids a different applicant from downloading someone else’s document', function () {
    $stranger = User::factory()->create();

    $response = $this->actingAs($stranger)->get(route('application.documents.download', [
        'application' => $this->application->id,
        'document' => $this->document->id,
    ]));

    $response->assertForbidden();
});

it('forbids non-SAO/admin staff roles from downloading another applicant’s document', function () {
    foreach ([RoleName::Lecturer, RoleName::Accountant, RoleName::Student] as $role) {
        $user = userWithRole($role);

        $this->actingAs($user)->get(route('application.documents.download', [
            'application' => $this->application->id,
            'document' => $this->document->id,
        ]))->assertForbidden();
    }
});

it('returns 404 when the document belongs to a different application', function () {
    $other = Application::factory()->submitted()->create();

    $response = $this->actingAs($this->applicant)->get(route('application.documents.download', [
        'application' => $other->id,
        'document' => $this->document->id,
    ]));

    $response->assertNotFound();
});

it('redirects guests to login', function () {
    $response = $this->get(route('application.documents.download', [
        'application' => $this->application->id,
        'document' => $this->document->id,
    ]));

    $response->assertRedirect(route('login'));
});
