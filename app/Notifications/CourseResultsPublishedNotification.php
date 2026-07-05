<?php

namespace App\Notifications;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourseResultsPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Course $course,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(config('app.name').' — '.$this->course->code.' results published')
            ->markdown('mail.course-results-published', [
                'course' => $this->course,
                'url' => route('student.results.index'),
            ]);
    }

    /**
     * The in-app payload. The student notification feed depends on this exact
     * shape — keep keys and value formats stable.
     *
     * @return array{type: string, course_id: int, course_code: string, course_title: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'course_results_published',
            'course_id' => $this->course->id,
            'course_code' => $this->course->code,
            'course_title' => $this->course->title,
        ];
    }
}
