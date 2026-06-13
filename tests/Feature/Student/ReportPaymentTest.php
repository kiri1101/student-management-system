<?php

use App\Enums\Bank;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\PaymentSubmission;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

function studentWithProfile(array $profileOverrides = []): StudentProfile
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::Student);

    return StudentProfile::factory()->create([
        'user_id' => $user->id,
        ...$profileOverrides,
    ]);
}

function paymentPayload(array $overrides = []): array
{
    return array_merge([
        'bank' => Bank::Uba->value,
        'amount_xaf' => 150000,
        'bank_reference' => 'TRX-12345',
        'slip' => UploadedFile::fake()->create('slip.pdf', 200, 'application/pdf'),
    ], $overrides);
}

it('lets a student report a deposit with a slip upload', function () {
    Storage::fake();
    $profile = studentWithProfile(['academic_year' => '2026']);

    $response = $this->actingAs($profile->user)
        ->post(route('student.payments.store'), paymentPayload());

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $submission = PaymentSubmission::sole();
    expect($submission->status)->toBe(PaymentStatus::Submitted)
        ->and($submission->student_profile_id)->toBe($profile->id)
        ->and($submission->academic_year)->toBe('2026')
        ->and($submission->amount_xaf)->toBe(150000);

    Storage::assertExists($submission->slip_path);
});

it('requires a slip', function () {
    Storage::fake();
    $profile = studentWithProfile();

    $response = $this->actingAs($profile->user)
        ->post(route('student.payments.store'), paymentPayload(['slip' => null]));

    $response->assertSessionHasErrors('slip');
    expect(PaymentSubmission::count())->toBe(0);
});

it('rejects a non-positive amount', function () {
    Storage::fake();
    $profile = studentWithProfile();

    $response = $this->actingAs($profile->user)
        ->post(route('student.payments.store'), paymentPayload(['amount_xaf' => 0]));

    $response->assertSessionHasErrors('amount_xaf');
});

it('refuses a payment from a user without a student enrollment', function () {
    Storage::fake();
    $admin = userWithRole(RoleName::Admin);

    $response = $this->actingAs($admin)
        ->post(route('student.payments.store'), paymentPayload());

    $response->assertSessionHasErrors('bank');
    expect(PaymentSubmission::count())->toBe(0);
});

it('lists only the student own submissions with the validated total', function () {
    $profile = studentWithProfile();
    PaymentSubmission::factory()->validated()->for($profile, 'studentProfile')->create(['amount_xaf' => 100000]);
    PaymentSubmission::factory()->validated()->for($profile, 'studentProfile')->create(['amount_xaf' => 50000]);
    PaymentSubmission::factory()->submitted()->for($profile, 'studentProfile')->create(['amount_xaf' => 30000]);

    // Another student's payment must not leak in.
    PaymentSubmission::factory()->validated()->create(['amount_xaf' => 999000]);

    $response = $this->actingAs($profile->user)->get(route('student.payments.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('student/payments/Index')
        ->has('submissions', 3)
        ->where('validatedTotal', 150000));
});

it('forbids non-student, non-admin roles', function () {
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->get(route('student.payments.index'))->assertForbidden();
});

it('redirects guests to login', function () {
    $this->get(route('student.payments.index'))->assertRedirect(route('login', absolute: false));
});
