<?php

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Events\ApplicationDecided;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('restores a soft-deleted prior profile, withdraws the current application, and writes audit', function () {
    Event::fake([ApplicationDecided::class]);

    $applicant = User::factory()->create();
    $prior = StudentProfile::factory()->create([
        'user_id' => $applicant->id,
        'matricule' => 'stm-2024-0007',
        'status' => StudentStatus::Active,
    ]);
    $prior->delete();

    $current = Application::factory()->submitted()->state(['user_id' => $applicant->id])->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.restorePrior', $current), [
        'prior_profile_id' => $prior->id,
        'notes' => 'Returning student.',
    ]);

    $response->assertRedirect(route('sao.applications.index'));

    expect($prior->fresh()->trashed())->toBeFalse()
        ->and($applicant->fresh()->hasRole(RoleName::Student))->toBeTrue();

    $fresh = $current->fresh();
    expect($fresh->status)->toBe(ApplicationStatus::Withdrawn)
        ->and($fresh->decided_by_user_id)->toBe($sao->id)
        ->and($fresh->decision_notes)->toContain("Merged into prior enrollment #{$prior->id}.")
        ->and($fresh->decision_notes)->toContain('Returning student.');

    $decided = AuditLog::query()
        ->where('subject_id', $current->id)
        ->where('action', AuditAction::ApplicationDecided->value)
        ->sole();
    expect($decided->context)->toMatchArray([
        'merged_into_prior' => true,
        'prior_profile_id' => $prior->id,
    ]);

    $statusChanged = AuditLog::query()
        ->where('subject_id', $current->id)
        ->where('action', AuditAction::StatusChanged->value)
        ->sole();
    expect($statusChanged->changes)->toBe(['before' => 'submitted', 'after' => 'withdrawn']);

    $roleAssigned = AuditLog::query()
        ->where('subject_type', $applicant->getMorphClass())
        ->where('subject_id', $applicant->id)
        ->where('action', AuditAction::RoleAssigned->value)
        ->sole();
    expect($roleAssigned->changes)->toBe(['role' => RoleName::Student->value]);

    Event::assertDispatched(ApplicationDecided::class);
});

it('rejects restoring a profile that is not trashed', function () {
    $applicant = User::factory()->create();
    $prior = StudentProfile::factory()->create(['user_id' => $applicant->id]);
    $current = Application::factory()->submitted()->state(['user_id' => $applicant->id])->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.restorePrior', $current), [
        'prior_profile_id' => $prior->id,
    ]);

    $response->assertSessionHasErrors('prior_profile_id');
    expect($current->fresh()->status)->toBe(ApplicationStatus::Submitted);
});

it('rejects restoring a profile that belongs to a different applicant', function () {
    $applicantA = User::factory()->create();
    $applicantB = User::factory()->create();

    $strangerProfile = StudentProfile::factory()->create(['user_id' => $applicantB->id]);
    $strangerProfile->delete();

    $current = Application::factory()->submitted()->state(['user_id' => $applicantA->id])->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.restorePrior', $current), [
        'prior_profile_id' => $strangerProfile->id,
    ]);

    $response->assertSessionHasErrors('prior_profile_id');
    expect($strangerProfile->fresh()->trashed())->toBeTrue();
});

it('refuses to restore against a terminal application', function () {
    $applicant = User::factory()->create();
    $prior = StudentProfile::factory()->create(['user_id' => $applicant->id]);
    $prior->delete();

    $current = Application::factory()->state([
        'user_id' => $applicant->id,
        'status' => ApplicationStatus::Admitted,
        'submitted_at' => now()->subDay(),
        'decided_at' => now(),
    ])->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.restorePrior', $current), [
        'prior_profile_id' => $prior->id,
    ]);

    $response->assertSessionHasErrors('status');
    expect($current->fresh()->status)->toBe(ApplicationStatus::Admitted)
        ->and($prior->fresh()->trashed())->toBeTrue();
});

it('rejects a missing prior_profile_id', function () {
    $applicant = User::factory()->create();
    $current = Application::factory()->submitted()->state(['user_id' => $applicant->id])->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.restorePrior', $current), []);

    $response->assertSessionHasErrors('prior_profile_id');
});
