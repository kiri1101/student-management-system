<?php

use App\Enums\RoleName;
use App\Models\PaymentSubmission;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

function paymentWithSlip(): PaymentSubmission
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

it('lets the owning student download their slip', function () {
    Storage::fake();
    $payment = paymentWithSlip();

    $this->actingAs($payment->studentProfile->user)
        ->get(route('payments.slip', $payment))
        ->assertOk()
        ->assertDownload('my-slip.pdf');
});

it('lets an accountant download any slip', function () {
    Storage::fake();
    $payment = paymentWithSlip();
    $accountant = userWithRole(RoleName::Accountant);

    $this->actingAs($accountant)
        ->get(route('payments.slip', $payment))
        ->assertOk();
});

it('lets an admin download any slip', function () {
    Storage::fake();
    $payment = paymentWithSlip();
    $admin = userWithRole(RoleName::Admin);

    $this->actingAs($admin)
        ->get(route('payments.slip', $payment))
        ->assertOk();
});

it('forbids a different student from downloading someone else slip', function () {
    Storage::fake();
    $payment = paymentWithSlip();

    $other = User::factory()->create();
    $other->assignRole(RoleName::Student);

    $this->actingAs($other)
        ->get(route('payments.slip', $payment))
        ->assertForbidden();
});

it('redirects guests to login', function () {
    Storage::fake();
    $payment = paymentWithSlip();

    $this->get(route('payments.slip', $payment))
        ->assertRedirect(route('login', absolute: false));
});
