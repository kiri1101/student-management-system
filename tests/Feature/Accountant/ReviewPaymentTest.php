<?php

use App\Enums\AuditAction;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Mail\PaymentReviewedMail;
use App\Models\AuditLog;
use App\Models\PaymentSubmission;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->accountant = userWithRole(RoleName::Accountant);
});

function submittedPaymentFor(string $email = 'student@example.com'): PaymentSubmission
{
    $user = User::factory()->create(['email' => $email]);
    $user->assignRole(RoleName::Student);
    $profile = StudentProfile::factory()->create(['user_id' => $user->id]);

    return PaymentSubmission::factory()->submitted()->for($profile, 'studentProfile')->create();
}

it('lets an accountant validate a submitted payment and emails the student', function () {
    Mail::fake();
    $payment = submittedPaymentFor('validate@example.com');

    $response = $this->actingAs($this->accountant)
        ->post(route('accountant.payments.validate', $payment));

    $response->assertRedirect(route('accountant.payments.index'));
    $response->assertSessionHasNoErrors();

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Validated)
        ->and($payment->reviewed_by)->toBe($this->accountant->id)
        ->and($payment->reviewed_at)->not->toBeNull();

    expect(AuditLog::where('action', AuditAction::PaymentValidated->value)
        ->where('subject_id', $payment->id)->exists())->toBeTrue();

    Mail::assertSent(PaymentReviewedMail::class, 1);
    Mail::assertSent(PaymentReviewedMail::class, fn (PaymentReviewedMail $mail): bool => $mail->hasTo('validate@example.com'));
});

it('lets an accountant reject a payment with a reason', function () {
    Mail::fake();
    $payment = submittedPaymentFor('reject@example.com');

    $response = $this->actingAs($this->accountant)
        ->post(route('accountant.payments.reject', $payment), [
            'rejection_reason' => 'Slip does not match the reported amount.',
        ]);

    $response->assertRedirect(route('accountant.payments.index'));
    $response->assertSessionHasNoErrors();

    $payment->refresh();
    expect($payment->status)->toBe(PaymentStatus::Rejected)
        ->and($payment->rejection_reason)->toBe('Slip does not match the reported amount.');

    expect(AuditLog::where('action', AuditAction::PaymentRejected->value)
        ->where('subject_id', $payment->id)->exists())->toBeTrue();

    Mail::assertSent(PaymentReviewedMail::class, 1);
});

it('requires a reason to reject', function () {
    $payment = submittedPaymentFor();

    $response = $this->actingAs($this->accountant)
        ->post(route('accountant.payments.reject', $payment), []);

    $response->assertSessionHasErrors('rejection_reason');
    expect($payment->fresh()->status)->toBe(PaymentStatus::Submitted);
});

it('refuses to re-review an already decided payment', function () {
    Mail::fake();
    $payment = submittedPaymentFor();

    // First validation succeeds; a second concurrent decision must be rejected
    // by the lockForUpdate re-guard inside the action.
    $this->actingAs($this->accountant)->post(route('accountant.payments.validate', $payment));

    $response = $this->actingAs($this->accountant)
        ->post(route('accountant.payments.validate', $payment));

    $response->assertSessionHasErrors('status');
    Mail::assertSent(PaymentReviewedMail::class, 1);
});

it('renders the payment queue', function () {
    submittedPaymentFor();

    $response = $this->actingAs($this->accountant)->get(route('accountant.payments.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('accountant/payments/Index')
        ->has('payments.data', 1)
        ->has('statusOptions', 3));
});

it('shows the payment status counts on the accountant dashboard', function () {
    PaymentSubmission::factory()->submitted()->create();
    PaymentSubmission::factory()->validated()->create();

    $response = $this->actingAs($this->accountant)->get(route('accountant.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboards/Accountant')
        ->where('statusCounts.submitted', 1)
        ->where('statusCounts.validated', 1));
});

it('lets an admin validate a payment too', function () {
    Mail::fake();
    $admin = userWithRole(RoleName::Admin);
    $payment = submittedPaymentFor();

    $this->actingAs($admin)
        ->post(route('accountant.payments.validate', $payment))
        ->assertRedirect(route('accountant.payments.index'));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Validated);
});

it('forbids non-accountant, non-admin roles from the review surface', function (RoleName $role) {
    $user = userWithRole($role);
    $payment = submittedPaymentFor();

    $this->actingAs($user)->get(route('accountant.payments.index'))->assertForbidden();
    $this->actingAs($user)->post(route('accountant.payments.validate', $payment))->assertForbidden();
})->with([
    'student' => [RoleName::Student],
    'sao' => [RoleName::Sao],
    'lecturer' => [RoleName::Lecturer],
    'applicant' => [RoleName::Applicant],
]);
