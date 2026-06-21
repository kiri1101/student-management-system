<?php

namespace App\Services;

use App\Enums\PaymentStanding;
use App\Enums\PaymentStatus;
use App\Models\FeeInstallment;
use App\Models\FeeSchedule;
use App\Models\PaymentSubmission;
use App\Models\StudentProfile;
use App\Models\TuitionDeferral;
use App\Support\PaymentStandingResult;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The single source of truth for tuition access-gating (#8). Standing is always
 * computed live — it depends on today's date — never stored. The rule:
 *
 *   required_so_far = Σ installment amounts whose due_date has passed
 *   cleared  ⇐ validated_paid ≥ required_so_far  (or no schedule configured)
 *   deferred ⇐ short, but an approved deferral's new_deadline ≥ today
 *   blocked  ⇐ short and no active deferral
 */
class PaymentStandingService
{
    /**
     * Compute a student's tuition standing as of `$asOf` (defaults to now).
     */
    public function for(StudentProfile $profile, ?CarbonInterface $asOf = null): PaymentStandingResult
    {
        $asOf ??= Carbon::now();

        $schedule = FeeSchedule::query()
            ->where('program_offering_id', $profile->program_offering_id)
            ->where('level', $profile->level)
            ->where('academic_year', $profile->academic_year)
            ->first();

        if ($schedule === null) {
            return new PaymentStandingResult(
                standing: PaymentStanding::Cleared,
                hasSchedule: false,
                totalXaf: 0,
                requiredSoFar: 0,
                validatedPaid: 0,
                shortfall: 0,
            );
        }

        $requiredSoFar = (int) FeeInstallment::query()
            ->where('fee_schedule_id', $schedule->id)
            ->whereDate('due_date', '<=', $asOf)
            ->sum('amount_xaf');

        $validatedPaid = (int) PaymentSubmission::query()
            ->where('student_profile_id', $profile->id)
            ->where('academic_year', $profile->academic_year)
            ->where('status', PaymentStatus::Validated->value)
            ->sum('amount_xaf');

        $shortfall = max($requiredSoFar - $validatedPaid, 0);

        if ($shortfall === 0) {
            return new PaymentStandingResult(
                standing: PaymentStanding::Cleared,
                hasSchedule: true,
                totalXaf: $schedule->total_xaf,
                requiredSoFar: $requiredSoFar,
                validatedPaid: $validatedPaid,
                shortfall: 0,
            );
        }

        $deferralDeadline = TuitionDeferral::query()
            ->where('student_profile_id', $profile->id)
            ->where('academic_year', $profile->academic_year)
            ->activeAsOf($asOf)
            ->max('new_deadline');

        return new PaymentStandingResult(
            standing: $deferralDeadline !== null ? PaymentStanding::Deferred : PaymentStanding::Blocked,
            hasSchedule: true,
            totalXaf: $schedule->total_xaf,
            requiredSoFar: $requiredSoFar,
            validatedPaid: $validatedPaid,
            shortfall: $shortfall,
            activeDeferralDeadline: $deferralDeadline === null ? null : Carbon::parse($deferralDeadline)->toDateString(),
        );
    }
}
