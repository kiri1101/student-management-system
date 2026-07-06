<?php

use App\Actions\Sao\DecideApplicationAction;
use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Events\ApplicationDecided;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\StudentProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 9, 1, 12, 0, 0));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('admits an application: creates a StudentProfile, assigns Student role, audits, dispatches event', function () {
    Event::fake([ApplicationDecided::class]);

    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.decide', $application), [
        'status' => 'admitted',
    ]);

    $response->assertRedirect(route('sao.applications.index'));

    $fresh = $application->fresh();
    expect($fresh->status)->toBe(ApplicationStatus::Admitted)
        ->and($fresh->decided_at)->not->toBeNull()
        ->and($fresh->decided_by_user_id)->toBe($sao->id);

    $applicant = $fresh->applicant;
    expect($applicant->hasRole(RoleName::Student))->toBeTrue();

    $profile = StudentProfile::sole();
    expect($profile->user_id)->toBe($applicant->id)
        ->and($profile->matricule)->toBe('stm-2026-0001')
        ->and($profile->program_offering_id)->toBe($application->program_offering_id)
        ->and($profile->level)->toBe($application->level)
        ->and($profile->academic_year)->toBe('2026')
        ->and($profile->status)->toBe(StudentStatus::Active);

    $decided = AuditLog::query()
        ->where('subject_type', $application->getMorphClass())
        ->where('subject_id', $application->id)
        ->where('action', AuditAction::ApplicationDecided->value)
        ->sole();
    expect($decided->changes)->toBe(['decision' => 'admitted', 'notes' => null])
        ->and($decided->user_id)->toBe($sao->id);

    $roleAssigned = AuditLog::query()
        ->where('subject_type', $applicant->getMorphClass())
        ->where('subject_id', $applicant->id)
        ->where('action', AuditAction::RoleAssigned->value)
        ->sole();
    expect($roleAssigned->changes)->toBe(['role' => RoleName::Student->value]);

    Event::assertDispatched(ApplicationDecided::class, fn (ApplicationDecided $e): bool => $e->application->id === $application->id && $e->decidedBy->id === $sao->id);
});

it('issues sequential matricules for the same admit year', function () {
    Event::fake([ApplicationDecided::class]);

    $sao = userWithRole(RoleName::Sao);
    $first = Application::factory()->submitted()->create();
    $second = Application::factory()->submitted()->create();

    $this->actingAs($sao)->post(route('sao.applications.decide', $first), ['status' => 'admitted']);
    $this->actingAs($sao)->post(route('sao.applications.decide', $second), ['status' => 'admitted']);

    expect(StudentProfile::query()->orderBy('id')->pluck('matricule')->all())
        ->toBe(['stm-2026-0001', 'stm-2026-0002']);
});

it('rejects an application without creating a profile but writes the decision audit', function () {
    Event::fake([ApplicationDecided::class]);

    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.decide', $application), [
        'status' => 'rejected',
        'notes' => 'Insufficient credentials.',
    ]);

    $response->assertRedirect(route('sao.applications.index'));
    expect($application->fresh()->status)->toBe(ApplicationStatus::Rejected)
        ->and(StudentProfile::count())->toBe(0)
        ->and($application->fresh()->applicant->hasRole(RoleName::Student))->toBeFalse();

    $decided = AuditLog::query()
        ->where('subject_id', $application->id)
        ->where('action', AuditAction::ApplicationDecided->value)
        ->sole();
    expect($decided->changes)->toBe(['decision' => 'rejected', 'notes' => 'Insufficient credentials.']);
});

it('requires notes when rejecting', function () {
    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.decide', $application), [
        'status' => 'rejected',
    ]);

    $response->assertSessionHasErrors('notes');
    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);
});

it('requires notes when waitlisting', function () {
    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.decide', $application), [
        'status' => 'waitlisted',
    ]);

    $response->assertSessionHasErrors('notes');
});

it('refuses Withdrawn through the decide endpoint', function () {
    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.decide', $application), [
        'status' => 'withdrawn',
    ]);

    $response->assertSessionHasErrors('status');
    expect($application->fresh()->status)->toBe(ApplicationStatus::Submitted);
});

it('refuses to re-decide a terminal application', function () {
    $application = Application::factory()->state([
        'status' => ApplicationStatus::Admitted,
        'submitted_at' => now()->subDay(),
        'decided_at' => now(),
    ])->create();
    $sao = userWithRole(RoleName::Sao);

    $response = $this->actingAs($sao)->post(route('sao.applications.decide', $application), [
        'status' => 'rejected',
        'notes' => 'second guess',
    ]);

    $response->assertSessionHasErrors('status');
    expect($application->fresh()->status)->toBe(ApplicationStatus::Admitted);
});

it('admits a returning applicant by restoring their trashed profile with a fresh matricule', function () {
    Event::fake([ApplicationDecided::class]);

    $application = Application::factory()->submitted()->create();
    $prior = StudentProfile::factory()->create([
        'user_id' => $application->user_id,
        'matricule' => 'stm-2024-0042',
        'level' => 2,
    ]);
    $prior->delete();

    $sao = userWithRole(RoleName::Sao);
    $response = $this->actingAs($sao)->post(route('sao.applications.decide', $application), [
        'status' => 'admitted',
        'acknowledged_prior_history' => true,
    ]);

    $response->assertRedirect(route('sao.applications.index'));
    $response->assertSessionDoesntHaveErrors();

    $profile = StudentProfile::sole();
    expect($profile->id)->toBe($prior->id)
        ->and($profile->trashed())->toBeFalse()
        ->and($profile->matricule)->toBe('stm-2026-0001')
        ->and($profile->level)->toBe($application->level)
        ->and($profile->program_offering_id)->toBe($application->program_offering_id)
        ->and($profile->status)->toBe(StudentStatus::Active)
        ->and($application->fresh()->status)->toBe(ApplicationStatus::Admitted)
        ->and($application->fresh()->applicant->hasRole(RoleName::Student))->toBeTrue();
});

it('keeps the matricule when admitting an applicant who already has an active profile', function () {
    Event::fake([ApplicationDecided::class]);

    $application = Application::factory()->submitted()->create(['level' => 3]);
    $profile = StudentProfile::factory()->create([
        'user_id' => $application->user_id,
        'matricule' => 'stm-2025-0007',
        'level' => 2,
    ]);
    $application->applicant->assignRole(RoleName::Student);

    $sao = userWithRole(RoleName::Sao);
    $this->actingAs($sao)->post(route('sao.applications.decide', $application), [
        'status' => 'admitted',
    ])->assertSessionDoesntHaveErrors();

    $fresh = $profile->fresh();
    expect($fresh->matricule)->toBe('stm-2025-0007')
        ->and($fresh->level)->toBe(3)
        ->and($fresh->academic_year)->toBe('2026');

    expect(AuditLog::query()
        ->where('action', AuditAction::RoleAssigned->value)
        ->where('subject_id', $application->user_id)
        ->count())->toBe(0);
});

it('refuses a decision when the application was finalized concurrently', function () {
    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    // Simulate a concurrent SAO winning the race after this request resolved
    // its (now stale) model instance.
    $stale = Application::query()->find($application->id);
    Application::query()->whereKey($application->id)->update([
        'status' => ApplicationStatus::Admitted->value,
        'decided_at' => now(),
    ]);

    try {
        app(DecideApplicationAction::class)
            ->execute($stale, ApplicationStatus::Rejected, 'second guess', $sao);
        $this->fail('Expected ValidationException for a concurrently finalized application.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('status');
    }

    expect($application->fresh()->status)->toBe(ApplicationStatus::Admitted);
});

it('does not reuse matricule numbers after a profile is force-deleted', function () {
    Event::fake([ApplicationDecided::class]);

    $sao = userWithRole(RoleName::Sao);
    $first = Application::factory()->submitted()->create();
    $this->actingAs($sao)->post(route('sao.applications.decide', $first), ['status' => 'admitted']);

    StudentProfile::sole()->forceDelete();

    $second = Application::factory()->submitted()->create();
    $this->actingAs($sao)->post(route('sao.applications.decide', $second), ['status' => 'admitted'])
        ->assertSessionDoesntHaveErrors();

    expect(StudentProfile::sole()->matricule)->toBe('stm-2026-0002');
});

it('records the prior-history acknowledgement in the audit context', function () {
    Event::fake([ApplicationDecided::class]);

    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('sao.applications.decide', $application), [
        'status' => 'admitted',
        'acknowledged_prior_history' => true,
    ]);

    $decided = AuditLog::query()
        ->where('subject_id', $application->id)
        ->where('action', AuditAction::ApplicationDecided->value)
        ->sole();

    expect($decided->context)->toMatchArray(['acknowledged_prior_history' => true]);
});
