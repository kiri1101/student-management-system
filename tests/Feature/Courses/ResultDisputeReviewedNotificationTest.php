<?php

use App\Enums\DisputeStatus;
use App\Enums\RoleName;
use App\Models\CourseResult;
use App\Models\ResultDispute;
use App\Models\StudentProfile;
use App\Notifications\ResultDisputeReviewedNotification;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    Notification::fake();
});

/**
 * An Open dispute by the given student on a published result of theirs.
 */
function disputeNotifOpenDispute(StudentProfile $student): ResultDispute
{
    $result = CourseResult::factory()->published()->create([
        'student_profile_id' => $student->id,
    ]);

    return ResultDispute::factory()->create([
        'course_result_id' => $result->id,
        'student_profile_id' => $student->id,
        'status' => DisputeStatus::Open->value,
    ]);
}

it('notifies the disputing student when a dispute is resolved, on mail and database', function () {
    $sao = userWithRole(RoleName::Sao);
    $student = StudentProfile::factory()->create();
    $dispute = disputeNotifOpenDispute($student);

    $this->actingAs($sao)
        ->post(route('sao.disputes.review', $dispute), [
            'status' => DisputeStatus::Resolved->value,
            'resolution_notes' => 'Exam mark corrected after recheck.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Notification::assertSentTo(
        $student->user,
        ResultDisputeReviewedNotification::class,
        function (ResultDisputeReviewedNotification $notification, array $channels): bool {
            return in_array('mail', $channels, true)
                && in_array('database', $channels, true)
                && $notification->dispute->status === DisputeStatus::Resolved;
        },
    );
});

it('notifies the disputing student when a dispute is rejected', function () {
    $sao = userWithRole(RoleName::Sao);
    $student = StudentProfile::factory()->create();
    $dispute = disputeNotifOpenDispute($student);

    $this->actingAs($sao)
        ->post(route('sao.disputes.review', $dispute), [
            'status' => DisputeStatus::Rejected->value,
            'resolution_notes' => 'Original mark stands.',
        ])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($student->user, ResultDisputeReviewedNotification::class);
});

it('sends nothing when a dispute is only moved to under review', function () {
    $sao = userWithRole(RoleName::Sao);
    $student = StudentProfile::factory()->create();
    $dispute = disputeNotifOpenDispute($student);

    $this->actingAs($sao)
        ->post(route('sao.disputes.review', $dispute), [
            'status' => DisputeStatus::UnderReview->value,
            'resolution_notes' => null,
        ])
        ->assertSessionHasNoErrors();

    Notification::assertNothingSent();
});
