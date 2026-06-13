<?php

use App\Actions\Accountant\ReviewPaymentAction;
use App\Enums\AuditAction;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\PaymentSubmission;
use App\Models\SchoolReceipt;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->accountant = userWithRole(RoleName::Accountant);
});

function submittedPayment(array $overrides = []): PaymentSubmission
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::Student);
    $profile = StudentProfile::factory()->create(['user_id' => $user->id]);

    return PaymentSubmission::factory()->submitted()->for($profile, 'studentProfile')->create([
        'academic_year' => '2026',
        'amount_xaf' => 200000,
        ...$overrides,
    ]);
}

function validate(PaymentSubmission $payment, User $accountant): PaymentSubmission
{
    return app(ReviewPaymentAction::class)->execute($payment, PaymentStatus::Validated, null, $accountant);
}

it('issues exactly one signed receipt when a payment is validated', function () {
    $payment = submittedPayment();

    validate($payment, $this->accountant);

    $receipt = SchoolReceipt::sole();
    expect($receipt->payment_submission_id)->toBe($payment->id)
        ->and($receipt->amount_xaf)->toBe(200000)
        ->and($receipt->receipt_number)->toStartWith('RCP-2026-')
        ->and($receipt->signature)->not->toBeEmpty()
        ->and($receipt->verifies())->toBeTrue();

    expect(AuditLog::where('action', AuditAction::ReceiptIssued->value)
        ->where('subject_id', $receipt->id)->exists())->toBeTrue();
});

it('does not issue a receipt on rejection', function () {
    $payment = submittedPayment();

    app(ReviewPaymentAction::class)->execute($payment, PaymentStatus::Rejected, 'bad slip', $this->accountant);

    expect(SchoolReceipt::count())->toBe(0);
});

it('increments the receipt number per year', function () {
    $first = validate(submittedPayment(), $this->accountant);
    $second = validate(submittedPayment(), $this->accountant);

    expect($first->schoolReceipt->receipt_number)->toBe('RCP-2026-00001')
        ->and($second->schoolReceipt->receipt_number)->toBe('RCP-2026-00002');
});

it('fails verification when the receipt is tampered with', function () {
    $payment = validate(submittedPayment(), $this->accountant);
    $receipt = $payment->schoolReceipt;

    // Tamper at the data layer (Eloquent update is blocked by immutability).
    DB::table('school_receipts')->where('id', $receipt->id)->update(['amount_xaf' => 999999]);

    expect($receipt->fresh()->verifies())->toBeFalse();
});

it('blocks updating a receipt', function () {
    $receipt = validate(submittedPayment(), $this->accountant)->schoolReceipt;

    expect(fn () => $receipt->update(['amount_xaf' => 1]))
        ->toThrow(RuntimeException::class);
});

it('blocks deleting a receipt', function () {
    $receipt = validate(submittedPayment(), $this->accountant)->schoolReceipt;

    expect(fn () => $receipt->delete())
        ->toThrow(RuntimeException::class);
});
