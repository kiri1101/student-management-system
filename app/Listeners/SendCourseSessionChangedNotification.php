<?php

namespace App\Listeners;

use App\Events\CourseSessionChanged;
use App\Notifications\CourseSessionChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class SendCourseSessionChangedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Fan out the session change to every active cohort student: each gets a
     * queued email plus an in-app database notification. Fires once per
     * cancel/reschedule of a future, currently-scheduled session.
     */
    public function handle(CourseSessionChanged $event): void
    {
        $users = $event->session->course->cohortStudents()
            ->with('user:id,name,email')
            ->get()
            ->pluck('user')
            ->filter();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send(
            $users,
            new CourseSessionChangedNotification(
                $event->session,
                $event->type,
                $event->reason,
                $event->previousScheduledFor,
            ),
        );
    }
}
