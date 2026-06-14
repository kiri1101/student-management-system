<?php

use App\Enums\AuditAction;
use App\Enums\DeferralStatus;
use App\Enums\RoleName;
use App\Mail\DeferralReviewedMail;
use App\Models\AuditLog;
use App\Models\StudentProfile;
use App\Models\TuitionDeferral;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->accountant = userWithRole(RoleName::Accountant);
});

function requestedDeferral(string $email = 'student@example.com'): TuitionDeferral
{
    $user = User::factory()->create(['email' => $email]);
    $user->assignRole(RoleName::Student);
    $profile = StudentProfile::factory()->create(['user_id' => $user->id]);

    return TuitionDeferral::factory()->requested()->for($profile, 'studentProfile')->create();
}

it('lets an accountant approve a deferral with an extended deadline and emails the student', function () {
    Mail::fake();
    $deferral = requestedDeferral('approve@example.com');
    $deadline = now()->addMonths(2)->toDateString();

    $response = $this->actingAs($this->accountant)
        ->post(route('accountant.deferrals.approve', $deferral), ['new_deadline' => $deadline]);

    $response->assertRedirect(route('accountant.deferrals.index'));
    $response->assertSessionHasNoErrors();

    $deferral->refresh();
    expect($deferral->status)->toBe(DeferralStatus::Approved)
        ->and($deferral->new_deadline->toDateString())->toBe($deadline)
        ->and($deferral->reviewed_by)->toBe($this->accountant->id);

    expect(AuditLog::where('action', AuditAction::DeferralApproved->value)
        ->where('subject_id', $deferral->id)->exists())->toBeTrue();

    Mail::assertSent(DeferralReviewedMail::class, fn (DeferralReviewedMail $m): bool => $m->hasTo('approve@example.com'));
});

it('requires an extended deadline to approve', function () {
    $deferral = requestedDeferral();

    $this->actingAs($this->accountant)
        ->post(route('accountant.deferrals.approve', $deferral), [])
        ->assertSessionHasErrors('new_deadline');
});

it('rejects an extended deadline in the past', function () {
    $deferral = requestedDeferral();

    $this->actingAs($this->accountant)
        ->post(route('accountant.deferrals.approve', $deferral), ['new_deadline' => now()->subDay()->toDateString()])
        ->assertSessionHasErrors('new_deadline');
});

it('lets an accountant reject a deferral with a reason', function () {
    Mail::fake();
    $deferral = requestedDeferral('reject@example.com');

    $response = $this->actingAs($this->accountant)
        ->post(route('accountant.deferrals.reject', $deferral), ['decision_notes' => 'Insufficient justification.']);

    $response->assertRedirect(route('accountant.deferrals.index'));
    $deferral->refresh();
    expect($deferral->status)->toBe(DeferralStatus::Rejected)
        ->and($deferral->decision_notes)->toBe('Insufficient justification.');

    expect(AuditLog::where('action', AuditAction::DeferralRejected->value)
        ->where('subject_id', $deferral->id)->exists())->toBeTrue();

    Mail::assertSent(DeferralReviewedMail::class, 1);
});

it('requires a reason to reject', function () {
    $deferral = requestedDeferral();

    $this->actingAs($this->accountant)
        ->post(route('accountant.deferrals.reject', $deferral), [])
        ->assertSessionHasErrors('decision_notes');
});

it('refuses to re-review an already decided deferral', function () {
    Mail::fake();
    $deferral = requestedDeferral();
    $deadline = now()->addMonth()->toDateString();

    $this->actingAs($this->accountant)->post(route('accountant.deferrals.approve', $deferral), ['new_deadline' => $deadline]);

    $this->actingAs($this->accountant)
        ->post(route('accountant.deferrals.approve', $deferral), ['new_deadline' => $deadline])
        ->assertSessionHasErrors('status');

    Mail::assertSent(DeferralReviewedMail::class, 1);
});

it('renders the deferral queue', function () {
    requestedDeferral();

    $response = $this->actingAs($this->accountant)->get(route('accountant.deferrals.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('accountant/deferrals/Index')
        ->has('deferrals.data', 1)
        ->has('statusOptions', 3));
});

it('shows the deferral counts on the accountant dashboard', function () {
    TuitionDeferral::factory()->requested()->create();
    TuitionDeferral::factory()->approved()->create();

    $response = $this->actingAs($this->accountant)->get(route('accountant.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboards/Accountant')
        ->where('deferralCounts.requested', 1)
        ->where('deferralCounts.approved', 1));
});

it('lets an admin approve a deferral too', function () {
    Mail::fake();
    $admin = userWithRole(RoleName::Admin);
    $deferral = requestedDeferral();

    $this->actingAs($admin)
        ->post(route('accountant.deferrals.approve', $deferral), ['new_deadline' => now()->addMonth()->toDateString()])
        ->assertRedirect(route('accountant.deferrals.index'));

    expect($deferral->fresh()->status)->toBe(DeferralStatus::Approved);
});

it('forbids non-accountant, non-admin roles from the deferral surface', function (RoleName $role) {
    $user = userWithRole($role);
    $deferral = requestedDeferral();

    $this->actingAs($user)->get(route('accountant.deferrals.index'))->assertForbidden();
    $this->actingAs($user)->post(route('accountant.deferrals.approve', $deferral), ['new_deadline' => now()->addMonth()->toDateString()])->assertForbidden();
})->with([
    'student' => [RoleName::Student],
    'sao' => [RoleName::Sao],
    'lecturer' => [RoleName::Lecturer],
    'applicant' => [RoleName::Applicant],
]);
