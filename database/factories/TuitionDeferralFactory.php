<?php

namespace Database\Factories;

use App\Enums\DeferralStatus;
use App\Models\StudentProfile;
use App\Models\TuitionDeferral;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TuitionDeferral>
 */
class TuitionDeferralFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_profile_id' => StudentProfile::factory(),
            'academic_year' => (string) now()->year,
            'reason' => fake()->sentence(),
            'requested_new_deadline' => now()->addMonth()->toDateString(),
            'status' => DeferralStatus::Requested->value,
            'new_deadline' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'decision_notes' => null,
        ];
    }

    public function requested(): static
    {
        return $this->state(fn (): array => [
            'status' => DeferralStatus::Requested->value,
            'new_deadline' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'decision_notes' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => DeferralStatus::Approved->value,
            'new_deadline' => now()->addMonths(2)->toDateString(),
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => DeferralStatus::Rejected->value,
            'new_deadline' => null,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'decision_notes' => fake()->sentence(),
        ]);
    }
}
