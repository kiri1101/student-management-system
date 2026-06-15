<?php

use App\Enums\AuditAction;
use App\Enums\DisputeStatus;
use App\Enums\ResultStatus;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\LecturerProfile;
use App\Models\ResultDispute;
use App\Models\StudentProfile;
use Database\Seeders\RolesSeeder;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

/**
 * Builds an open dispute on a published result owned by a cohort student.
 */
function c4ReviewOpenDispute(): ResultDispute
{
    $lecturer = LecturerProfile::factory()->create();
    $course = Course::factory()->approved()->create(['lecturer_profile_id' => $lecturer->id]);

    $student = StudentProfile::factory()->create([
        'program_offering_id' => $course->program_offering_id,
        'level' => $course->level,
        'academic_year' => $course->academic_year,
        'status' => StudentStatus::Active->value,
    ]);

    $result = CourseResult::factory()->create([
        'course_id' => $course->id,
        'student_profile_id' => $student->id,
        'ca_score' => 70,
        'exam_score' => 80,
        'status' => ResultStatus::Published->value,
        'published_at' => now(),
    ]);

    return ResultDispute::factory()->create([
        'course_result_id' => $result->id,
        'student_profile_id' => $student->id,
        'reason' => 'My exam mark is wrong.',
        'status' => DisputeStatus::Open->value,
    ]);
}

it('lets the SAO resolve an open dispute with notes', function () {
    $sao = userWithRole(RoleName::Sao);
    $dispute = c4ReviewOpenDispute();

    $response = $this->actingAs($sao)
        ->post(route('sao.disputes.review', $dispute), [
            'status' => DisputeStatus::Resolved->value,
            'resolution_notes' => 'Re-marked; the original score stands.',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $fresh = $dispute->fresh();
    expect($fresh->status)->toBe(DisputeStatus::Resolved)
        ->and($fresh->reviewed_by)->toBe($sao->id)
        ->and($fresh->reviewed_at)->not->toBeNull()
        ->and($fresh->resolution_notes)->toBe('Re-marked; the original score stands.');

    expect(AuditLog::query()
        ->where('subject_type', $dispute->getMorphClass())
        ->where('subject_id', $dispute->id)
        ->where('action', AuditAction::DisputeResolved->value)
        ->exists())->toBeTrue();
});

it('moves an open dispute to under review without resolving it', function () {
    $sao = userWithRole(RoleName::Sao);
    $dispute = c4ReviewOpenDispute();

    $this->actingAs($sao)
        ->post(route('sao.disputes.review', $dispute), [
            'status' => DisputeStatus::UnderReview->value,
            'resolution_notes' => 'Investigating with the lecturer.',
        ])
        ->assertSessionHasNoErrors();

    $fresh = $dispute->fresh();
    expect($fresh->status)->toBe(DisputeStatus::UnderReview)
        ->and($fresh->reviewed_at)->toBeNull()
        ->and($fresh->reviewed_by)->toBeNull();

    expect(AuditLog::query()
        ->where('subject_type', $dispute->getMorphClass())
        ->where('subject_id', $dispute->id)
        ->where('action', AuditAction::DisputeResolved->value)
        ->exists())->toBeFalse();
});

it('lets the SAO reject an open dispute', function () {
    $sao = userWithRole(RoleName::Sao);
    $dispute = c4ReviewOpenDispute();

    $this->actingAs($sao)
        ->post(route('sao.disputes.review', $dispute), [
            'status' => DisputeStatus::Rejected->value,
            'resolution_notes' => 'No discrepancy found.',
        ])
        ->assertSessionHasNoErrors();

    $fresh = $dispute->fresh();
    expect($fresh->status)->toBe(DisputeStatus::Rejected)
        ->and($fresh->reviewed_by)->toBe($sao->id)
        ->and($fresh->reviewed_at)->not->toBeNull();

    expect(AuditLog::query()
        ->where('subject_type', $dispute->getMorphClass())
        ->where('subject_id', $dispute->id)
        ->where('action', AuditAction::DisputeResolved->value)
        ->exists())->toBeTrue();
});

it('rejects reviewing an already-resolved dispute', function () {
    $sao = userWithRole(RoleName::Sao);
    $dispute = c4ReviewOpenDispute();
    $dispute->update([
        'status' => DisputeStatus::Resolved->value,
        'reviewed_by' => $sao->id,
        'reviewed_at' => now(),
        'resolution_notes' => 'Already settled.',
    ]);

    $this->actingAs($sao)
        ->post(route('sao.disputes.review', $dispute), [
            'status' => DisputeStatus::Rejected->value,
            'resolution_notes' => 'Trying to re-decide.',
        ])
        ->assertSessionHasErrors('status');

    expect($dispute->fresh()->status)->toBe(DisputeStatus::Resolved);
});

it('forbids a non-SAO from reviewing a dispute', function () {
    $studentUser = userWithRole(RoleName::Student);
    $dispute = c4ReviewOpenDispute();

    $this->actingAs($studentUser)
        ->post(route('sao.disputes.review', $dispute), [
            'status' => DisputeStatus::Resolved->value,
            'resolution_notes' => 'Should be blocked.',
        ])
        ->assertForbidden();

    expect($dispute->fresh()->status)->toBe(DisputeStatus::Open);
});
