<?php

use App\Enums\RoleName;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

test('the api/v1 lookup endpoints are rate limited per user', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('lookups'.$user->id), amount: 60);

    $this->actingAs($user)
        ->getJson(route('api.v1.program-offerings.index'))
        ->assertTooManyRequests();
});

test('the document download is rate limited per user', function () {
    Storage::fake('local');

    $applicant = User::factory()->create();
    $application = Application::factory()->submitted()->state([
        'user_id' => $applicant->id,
    ])->create();

    $path = UploadedFile::fake()->create('nid.pdf', 100, 'application/pdf')->store('applications', 'local');
    $document = ApplicationDocument::factory()->create([
        'application_id' => $application->id,
        'file_path' => $path,
        'original_filename' => 'national-id.pdf',
        'mime_type' => 'application/pdf',
    ]);

    RateLimiter::increment(md5('lookups'.$applicant->id), amount: 60);

    $this->actingAs($applicant)
        ->get(route('application.documents.download', [
            'application' => $application->id,
            'document' => $document->id,
        ]))
        ->assertTooManyRequests();
});

test('the admin audit-log endpoint is rate limited per user', function () {
    $this->seed(RolesSeeder::class);
    $admin = userWithRole(RoleName::Admin);

    RateLimiter::increment(md5('audit-logs'.$admin->id), amount: 30);

    $this->actingAs($admin)
        ->getJson(route('admin.audit-logs.index'))
        ->assertTooManyRequests();
});
