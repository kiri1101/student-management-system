<?php

use App\Enums\PaymentStanding;
use App\Models\FeeInstallment;
use App\Models\FeeSchedule;
use App\Models\PaymentSubmission;
use App\Models\StudentProfile;
use App\Models\TuitionDeferral;
use App\Services\PaymentStandingService;
use App\Support\PaymentStandingResult;
use Illuminate\Support\Carbon;

const AS_OF = '2026-06-01';
const PAST_DUE = '2026-03-01';
const FUTURE_DUE = '2026-09-01';

/**
 * @param  array<int, array{0:int,1:int,2:string}>  $installments  [sequence, amount_xaf, due_date]
 */
function profileWithSchedule(array $installments): StudentProfile
{
    $profile = StudentProfile::factory()->create(['level' => 1, 'academic_year' => '2026']);

    $schedule = FeeSchedule::factory()->create([
        'program_offering_id' => $profile->program_offering_id,
        'level' => 1,
        'academic_year' => '2026',
        'total_xaf' => 500000,
    ]);

    foreach ($installments as [$sequence, $amount, $due]) {
        FeeInstallment::factory()->create([
            'fee_schedule_id' => $schedule->id,
            'sequence' => $sequence,
            'amount_xaf' => $amount,
            'due_date' => $due,
        ]);
    }

    return $profile;
}

function standing(StudentProfile $profile): PaymentStandingResult
{
    return app(PaymentStandingService::class)->for($profile, Carbon::parse(AS_OF));
}

it('clears a student with no fee schedule configured', function () {
    $profile = StudentProfile::factory()->create(['level' => 1, 'academic_year' => '2026']);

    $result = standing($profile);

    expect($result->standing)->toBe(PaymentStanding::Cleared)
        ->and($result->hasSchedule)->toBeFalse();
});

it('clears a student when no installment is due yet', function () {
    $profile = profileWithSchedule([[1, 300000, FUTURE_DUE]]);

    $result = standing($profile);

    expect($result->standing)->toBe(PaymentStanding::Cleared)
        ->and($result->requiredSoFar)->toBe(0);
});

it('blocks a student who has paid nothing against a passed installment', function () {
    $profile = profileWithSchedule([[1, 300000, PAST_DUE]]);

    $result = standing($profile);

    expect($result->standing)->toBe(PaymentStanding::Blocked)
        ->and($result->requiredSoFar)->toBe(300000)
        ->and($result->validatedPaid)->toBe(0)
        ->and($result->shortfall)->toBe(300000);
});

it('clears a student whose validated payments cover the passed installments', function () {
    $profile = profileWithSchedule([[1, 300000, PAST_DUE]]);
    PaymentSubmission::factory()->validated()->create([
        'student_profile_id' => $profile->id,
        'academic_year' => '2026',
        'amount_xaf' => 300000,
    ]);

    $result = standing($profile);

    expect($result->standing)->toBe(PaymentStanding::Cleared)
        ->and($result->validatedPaid)->toBe(300000)
        ->and($result->shortfall)->toBe(0);
});

it('only counts validated payments, not submitted ones', function () {
    $profile = profileWithSchedule([[1, 300000, PAST_DUE]]);
    PaymentSubmission::factory()->submitted()->create([
        'student_profile_id' => $profile->id,
        'academic_year' => '2026',
        'amount_xaf' => 300000,
    ]);

    expect(standing($profile)->standing)->toBe(PaymentStanding::Blocked);
});

it('sums every installment whose due date has passed', function () {
    $profile = profileWithSchedule([
        [1, 300000, PAST_DUE],
        [2, 200000, FUTURE_DUE],
    ]);
    PaymentSubmission::factory()->validated()->create([
        'student_profile_id' => $profile->id,
        'academic_year' => '2026',
        'amount_xaf' => 300000,
    ]);

    // Only the first installment is due by AS_OF, and it's covered.
    expect(standing($profile)->standing)->toBe(PaymentStanding::Cleared)
        ->and(standing($profile)->requiredSoFar)->toBe(300000);
});

it('defers a short student who has an active approved deferral', function () {
    $profile = profileWithSchedule([[1, 300000, PAST_DUE]]);
    TuitionDeferral::factory()->approved()->create([
        'student_profile_id' => $profile->id,
        'academic_year' => '2026',
        'new_deadline' => '2026-08-01',
    ]);

    $result = standing($profile);

    expect($result->standing)->toBe(PaymentStanding::Deferred)
        ->and($result->activeDeferralDeadline)->toBe('2026-08-01');
});

it('blocks a short student whose deferral deadline has already passed', function () {
    $profile = profileWithSchedule([[1, 300000, PAST_DUE]]);
    TuitionDeferral::factory()->approved()->create([
        'student_profile_id' => $profile->id,
        'academic_year' => '2026',
        'new_deadline' => '2026-04-01',
    ]);

    expect(standing($profile)->standing)->toBe(PaymentStanding::Blocked);
});

it('blocks a short student whose deferral is only requested, not approved', function () {
    $profile = profileWithSchedule([[1, 300000, PAST_DUE]]);
    TuitionDeferral::factory()->requested()->create([
        'student_profile_id' => $profile->id,
        'academic_year' => '2026',
    ]);

    expect(standing($profile)->standing)->toBe(PaymentStanding::Blocked);
});
