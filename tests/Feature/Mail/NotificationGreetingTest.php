<?php

use App\Enums\SessionChangeType;
use App\Models\Course;
use App\Models\CourseSession;
use App\Models\ResultDispute;
use App\Models\User;
use App\Notifications\CourseResultsPublishedNotification;
use App\Notifications\CourseSessionChangedNotification;
use App\Notifications\ResultDisputeReviewedNotification;
use Illuminate\Mail\Markdown;

/**
 * Renders a notification's mail markdown the way the mail channel does, so the
 * greeting is actually exercised (Notification::fake() never renders the view).
 */
function renderNotificationMail(object $notification, User $user): string
{
    $mail = $notification->toMail($user);

    return (string) app(Markdown::class)->render($mail->markdown, $mail->data());
}

it('greets the recipient by name in the results-published mail', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);
    $course = Course::factory()->approved()->create();

    $html = renderNotificationMail(new CourseResultsPublishedNotification($course), $user);

    expect($html)->toContain('Ada Lovelace')->not->toContain('Hi there,');
});

it('greets the recipient by name in the dispute-reviewed mail', function () {
    $user = User::factory()->create(['name' => 'Grace Hopper']);
    $dispute = ResultDispute::factory()->resolved()->create();

    $html = renderNotificationMail(new ResultDisputeReviewedNotification($dispute), $user);

    expect($html)->toContain('Grace Hopper')->not->toContain('Hi there,');
});

it('greets the recipient by name in the session-changed mail', function () {
    $user = User::factory()->create(['name' => 'Alan Turing']);
    $session = CourseSession::factory()->scheduled()->create(['scheduled_for' => now()->addWeek()]);

    $html = renderNotificationMail(
        new CourseSessionChangedNotification($session, SessionChangeType::Cancelled),
        $user,
    );

    expect($html)->toContain('Alan Turing')->not->toContain('Hi there,');
});
