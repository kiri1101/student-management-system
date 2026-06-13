<?php

use App\Actions\Accountant\ReviewPaymentAction;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
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

function validatedReceipt(string $name = 'Ada Lovelace'): SchoolReceipt
{
    $user = User::factory()->create(['name' => $name]);
    $user->assignRole(RoleName::Student);
    $profile = StudentProfile::factory()->create(['user_id' => $user->id, 'matricule' => 'stm-2026-0011']);
    $payment = PaymentSubmission::factory()->submitted()->for($profile, 'studentProfile')->create([
        'academic_year' => '2026',
        'amount_xaf' => 175000,
    ]);

    $accountant = userWithRole(RoleName::Accountant);
    app(ReviewPaymentAction::class)->execute($payment, PaymentStatus::Validated, null, $accountant);

    return $payment->fresh()->schoolReceipt;
}

it('verifies an authentic receipt and binds the identity, without auth', function () {
    $receipt = validatedReceipt('Ada Lovelace');

    $response = $this->get(route('receipts.verify', $receipt->receipt_number));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('receipts/Verify')
        ->where('valid', true)
        ->where('receipt.student.matricule', 'stm-2026-0011')
        ->where('receipt.student.name', 'Ada Lovelace')
        ->where('receipt.amount_xaf', 175000));
});

it('reports invalid for an unknown receipt number', function () {
    $response = $this->get(route('receipts.verify', 'RCP-2026-99999'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('receipts/Verify')
        ->where('valid', false)
        ->where('receipt', null));
});

it('reports invalid for a tampered receipt', function () {
    $receipt = validatedReceipt();
    DB::table('school_receipts')->where('id', $receipt->id)->update(['amount_xaf' => 1]);

    $response = $this->get(route('receipts.verify', $receipt->receipt_number));

    $response->assertInertia(fn ($page) => $page
        ->where('valid', false)
        ->where('receipt', null));
});

it('lets the owning student view their printable receipt', function () {
    $receipt = validatedReceipt();
    $student = $receipt->paymentSubmission->studentProfile->user;

    $response = $this->actingAs($student)
        ->get(route('student.payments.receipt', $receipt->paymentSubmission));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('student/payments/Receipt')
        ->where('receipt.receipt_number', $receipt->receipt_number)
        ->has('verifyUrl'));
});

it('forbids a different student from viewing someone else receipt', function () {
    $receipt = validatedReceipt();
    $other = User::factory()->create();
    $other->assignRole(RoleName::Student);

    $this->actingAs($other)
        ->get(route('student.payments.receipt', $receipt->paymentSubmission))
        ->assertForbidden();
});

it('404s the receipt page for an unvalidated payment', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Student);
    $profile = StudentProfile::factory()->create(['user_id' => $user->id]);
    $payment = PaymentSubmission::factory()->submitted()->for($profile, 'studentProfile')->create();

    $this->actingAs($user)
        ->get(route('student.payments.receipt', $payment))
        ->assertNotFound();
});
