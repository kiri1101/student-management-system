<?php

namespace Database\Factories;

use App\Enums\Bank;
use App\Enums\PaymentStatus;
use App\Models\PaymentSubmission;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentSubmission>
 */
class PaymentSubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_profile_id' => StudentProfile::factory(),
            'academic_year' => (string) now()->year,
            'bank' => fake()->randomElement(Bank::cases())->value,
            'amount_xaf' => fake()->numberBetween(25, 300) * 1000,
            'bank_reference' => strtoupper(Str::random(10)),
            'slip_path' => 'payment-slips/'.Str::uuid().'.pdf',
            'slip_original_filename' => 'bank-slip.pdf',
            'slip_mime_type' => 'application/pdf',
            'status' => PaymentStatus::Submitted->value,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Submitted->value,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function validated(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Validated->value,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Rejected->value,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
