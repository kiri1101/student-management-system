<?php

use App\Enums\RoleName;
use App\Enums\SessionChangeType;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\User;
use App\Notifications\CourseSessionChangedNotification;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
    // Intercept the mail channel so notify() still persists the database row
    // synchronously (QUEUE_CONNECTION=sync) without dispatching real mail.
    Mail::fake();
});

/**
 * Creates a user holding the Student role (sufficient for the notification routes).
 */
function studentNotifUser(): User
{
    return userWithRole(RoleName::Student);
}

/**
 * Builds a persisted, cancelled CourseSession to back a real notification payload.
 */
function studentNotifSession(): CourseSession
{
    $course = Course::factory()->approved()->assigned()->create();

    return CourseSession::factory()->cancelled()->create([
        'course_id' => $course->id,
        'scheduled_for' => now()->addWeek(),
    ]);
}

/**
 * Persists a database notification on the given user and returns its id.
 */
function studentNotifSeed(User $user): string
{
    $user->notify(new CourseSessionChangedNotification(
        studentNotifSession(),
        SessionChangeType::Cancelled,
        'Lecturer unavailable.',
        null,
    ));

    return (string) $user->notifications()->latest()->first()->id;
}

it('marks a single notification as read for its owner', function () {
    $user = studentNotifUser();
    $notificationId = studentNotifSeed($user);

    expect($user->unreadNotifications()->count())->toBe(1);

    $this->actingAs($user)
        ->post(route('student.notifications.read', $notificationId))
        ->assertRedirect();

    expect($user->fresh()->notifications()->find($notificationId)->read_at)->not->toBeNull()
        ->and($user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('marks all notifications as read for its owner', function () {
    $user = studentNotifUser();
    studentNotifSeed($user);
    studentNotifSeed($user);

    expect($user->unreadNotifications()->count())->toBe(2);

    $this->actingAs($user)
        ->post(route('student.notifications.read-all'))
        ->assertRedirect();

    $fresh = $user->fresh();
    expect($fresh->unreadNotifications()->count())->toBe(0)
        ->and($fresh->notifications()->whereNull('read_at')->count())->toBe(0);
});

it('forbids a student from marking another student notification as read', function () {
    $owner = studentNotifUser();
    $intruder = studentNotifUser();
    $notificationId = studentNotifSeed($owner);

    $this->actingAs($intruder)
        ->post(route('student.notifications.read', $notificationId))
        ->assertForbidden();

    expect($owner->fresh()->notifications()->find($notificationId)->read_at)->toBeNull()
        ->and($owner->fresh()->unreadNotifications()->count())->toBe(1);
});
