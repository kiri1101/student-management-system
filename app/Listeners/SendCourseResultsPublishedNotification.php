<?php

namespace App\Listeners;

use App\Events\CourseResultsPublished;
use App\Models\StudentProfile;
use App\Notifications\CourseResultsPublishedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class SendCourseResultsPublishedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Notify each student whose result was just published, on mail + in-app.
     * Recipients are the exact set captured at publish time, so a re-publish
     * for late marks notifies only the newly-published students.
     */
    public function handle(CourseResultsPublished $event): void
    {
        $users = StudentProfile::query()
            ->whereIn('id', $event->studentProfileIds)
            ->with('user:id,name,email')
            ->get()
            ->pluck('user')
            ->filter();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new CourseResultsPublishedNotification($event->course));
    }
}
