<?php

use App\Actions\Sao\RestorePriorEnrollment;
use App\Enums\RoleName;
use App\Mail\ApplicationDecisionMail;
use App\Models\Application;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\Mail;

it('emails the applicant when admitted, including their matricule', function () {
    Mail::fake();

    $application = Application::factory()->submitted()->create([
        'contact_email' => 'applicant@example.com',
    ]);
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('sao.applications.decide', $application), [
        'status' => 'admitted',
    ])->assertSessionDoesntHaveErrors();

    Mail::assertSent(ApplicationDecisionMail::class, 1);
    Mail::assertSent(
        ApplicationDecisionMail::class,
        fn (ApplicationDecisionMail $mail): bool => $mail->hasTo('applicant@example.com'),
    );

    $rendered = (new ApplicationDecisionMail($application->fresh()))->render();
    expect($rendered)->toContain('admitted')
        ->and($rendered)->toContain(StudentProfile::sole()->matricule);
});

it('emails the applicant on rejection with the decision notes', function () {
    Mail::fake();

    $application = Application::factory()->submitted()->create([
        'contact_email' => 'rejected@example.com',
    ]);
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('sao.applications.decide', $application), [
        'status' => 'rejected',
        'notes' => 'Missing transcript.',
    ])->assertSessionDoesntHaveErrors();

    Mail::assertSent(ApplicationDecisionMail::class, 1);
    Mail::assertSent(
        ApplicationDecisionMail::class,
        fn (ApplicationDecisionMail $mail): bool => $mail->hasTo('rejected@example.com'),
    );

    $rendered = (new ApplicationDecisionMail($application->fresh()))->render();
    expect($rendered)->toContain('not been admitted')
        ->and($rendered)->toContain('Missing transcript.');
});

it('emails the applicant when waitlisted', function () {
    Mail::fake();

    $application = Application::factory()->submitted()->create([
        'contact_email' => 'waitlisted@example.com',
    ]);
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('sao.applications.decide', $application), [
        'status' => 'waitlisted',
        'notes' => 'Cohort is full.',
    ])->assertSessionDoesntHaveErrors();

    Mail::assertSent(ApplicationDecisionMail::class, 1);

    $rendered = (new ApplicationDecisionMail($application->fresh()))->render();
    expect($rendered)->toContain('waiting list');
});

it('emails the returning student when their prior enrollment is restored', function () {
    Mail::fake();

    $application = Application::factory()->submitted()->create([
        'contact_email' => 'returning@example.com',
    ]);
    $prior = StudentProfile::factory()->create([
        'user_id' => $application->user_id,
        'matricule' => 'stm-2024-0042',
    ]);
    $prior->delete();

    $sao = userWithRole(RoleName::Sao);

    app(RestorePriorEnrollment::class)->execute(
        $application->applicant,
        $prior,
        $application,
        $sao,
    );

    Mail::assertSent(ApplicationDecisionMail::class, 1);
    Mail::assertSent(
        ApplicationDecisionMail::class,
        fn (ApplicationDecisionMail $mail): bool => $mail->hasTo('returning@example.com'),
    );

    $rendered = (new ApplicationDecisionMail($application->fresh()))->render();
    expect($rendered)->toContain('existing student record')
        ->and($rendered)->toContain('stm-2024-0042');
});

it('sends nothing for a non-terminal triage move', function () {
    Mail::fake();

    $application = Application::factory()->submitted()->create();
    $sao = userWithRole(RoleName::Sao);

    $this->actingAs($sao)->post(route('sao.applications.triage', $application), [
        'status' => 'under_review',
    ])->assertSessionDoesntHaveErrors();

    Mail::assertNothingSent();
});
