<?php

use App\Enums\RoleName;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\PaymentSubmission;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// Feature/Files is not covered by the RolesSeeder beforeEach binding in
// tests/Pest.php (which only targets Auth, Dashboards, Admin, Applications,
// Sao and Browser), so we seed roles inline here.
beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

function inlineViewPaymentWithSlip(): PaymentSubmission
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::Student);
    $profile = StudentProfile::factory()->create(['user_id' => $user->id]);

    $payment = PaymentSubmission::factory()->for($profile, 'studentProfile')->create([
        'slip_path' => 'payment-slips/slip.pdf',
        'slip_original_filename' => 'my-slip.pdf',
    ]);

    Storage::put($payment->slip_path, 'pretend-pdf-bytes');

    return $payment;
}

/*
|--------------------------------------------------------------------------
| Payment slip inline view — same auth as payments.slip download
|--------------------------------------------------------------------------
*/

it('streams the slip inline to the owning student', function () {
    Storage::fake();
    $payment = inlineViewPaymentWithSlip();

    $response = $this->actingAs($payment->studentProfile->user)
        ->get(route('payments.slip.view', $payment));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('inline');
});

it('streams the slip inline to an accountant', function () {
    Storage::fake();
    $payment = inlineViewPaymentWithSlip();
    $accountant = userWithRole(RoleName::Accountant);

    $response = $this->actingAs($accountant)
        ->get(route('payments.slip.view', $payment));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('inline');
});

it('streams the slip inline to an admin', function () {
    Storage::fake();
    $payment = inlineViewPaymentWithSlip();
    $admin = userWithRole(RoleName::Admin);

    $response = $this->actingAs($admin)
        ->get(route('payments.slip.view', $payment));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('inline');
});

it('forbids a different student from viewing someone else slip', function () {
    Storage::fake();
    $payment = inlineViewPaymentWithSlip();

    $other = User::factory()->create();
    $other->assignRole(RoleName::Student);

    $this->actingAs($other)
        ->get(route('payments.slip.view', $payment))
        ->assertForbidden();
});

it('redirects guests to login from the slip view route', function () {
    Storage::fake();
    $payment = inlineViewPaymentWithSlip();

    $this->get(route('payments.slip.view', $payment))
        ->assertRedirect(route('login', absolute: false));
});

/*
|--------------------------------------------------------------------------
| Application document inline view — same auth as application.documents.download
|--------------------------------------------------------------------------
*/

function inlineViewApplicationDocument(User $applicant): array
{
    $application = Application::factory()->submitted()->state([
        'user_id' => $applicant->id,
    ])->create();

    $file = UploadedFile::fake()->create('nid.pdf', 100, 'application/pdf');
    $path = $file->store('applications', 'local');

    $document = ApplicationDocument::factory()->create([
        'application_id' => $application->id,
        'file_path' => $path,
        'original_filename' => 'national-id.pdf',
        'mime_type' => 'application/pdf',
    ]);

    return [$application, $document];
}

it('streams an application document inline to its owner', function () {
    Storage::fake('local');
    $applicant = User::factory()->create();
    [$application, $document] = inlineViewApplicationDocument($applicant);

    $response = $this->actingAs($applicant)->get(route('application.documents.view', [
        'application' => $application->id,
        'document' => $document->id,
    ]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('inline');
});

it('streams an application document inline to a SAO', function () {
    Storage::fake('local');
    $applicant = User::factory()->create();
    [$application, $document] = inlineViewApplicationDocument($applicant);
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->get(route('application.documents.view', [
        'application' => $application->id,
        'document' => $document->id,
    ]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('inline');
});

it('streams an application document inline to an admin', function () {
    Storage::fake('local');
    $applicant = User::factory()->create();
    [$application, $document] = inlineViewApplicationDocument($applicant);
    $admin = userWithRole(RoleName::Admin);

    $response = $this->actingAs($admin)->get(route('application.documents.view', [
        'application' => $application->id,
        'document' => $document->id,
    ]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('inline');
});

it('forbids an unrelated applicant from viewing an application document', function () {
    Storage::fake('local');
    $applicant = User::factory()->create();
    [$application, $document] = inlineViewApplicationDocument($applicant);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('application.documents.view', [
        'application' => $application->id,
        'document' => $document->id,
    ]))->assertForbidden();
});

it('forbids non-SAO/admin staff roles from viewing another applicant document', function () {
    Storage::fake('local');
    $applicant = User::factory()->create();
    [$application, $document] = inlineViewApplicationDocument($applicant);

    foreach ([RoleName::Lecturer, RoleName::Accountant, RoleName::Student] as $role) {
        $user = userWithRole($role);

        $this->actingAs($user)->get(route('application.documents.view', [
            'application' => $application->id,
            'document' => $document->id,
        ]))->assertForbidden();
    }
});

it('returns 404 when the document belongs to a different application', function () {
    Storage::fake('local');
    $applicant = User::factory()->create();
    [, $document] = inlineViewApplicationDocument($applicant);
    $other = Application::factory()->submitted()->create();

    $this->actingAs($applicant)->get(route('application.documents.view', [
        'application' => $other->id,
        'document' => $document->id,
    ]))->assertNotFound();
});

it('redirects guests to login from the document view route', function () {
    Storage::fake('local');
    $applicant = User::factory()->create();
    [$application, $document] = inlineViewApplicationDocument($applicant);

    $this->get(route('application.documents.view', [
        'application' => $application->id,
        'document' => $document->id,
    ]))->assertRedirect(route('login'));
});
