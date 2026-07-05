<?php

namespace App\Notifications;

use App\Models\ResultDispute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResultDisputeReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ResultDispute $dispute,
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
        $course = $this->dispute->courseResult->course;

        return (new MailMessage)
            ->subject(config('app.name').' — dispute reviewed')
            ->markdown('mail.result-dispute-reviewed', [
                'course' => $course,
                'status' => $this->dispute->status->value,
                'resolutionNotes' => $this->dispute->resolution_notes,
                'url' => route('student.results.index'),
            ]);
    }

    /**
     * The in-app payload. The student notification feed depends on this exact
     * shape — keep keys and value formats stable.
     *
     * @return array{type: string, course_id: int, course_code: string, course_title: string, dispute_id: int, status: string, resolution_notes: string|null}
     */
    public function toArray(object $notifiable): array
    {
        $course = $this->dispute->courseResult->course;

        return [
            'type' => 'result_dispute_reviewed',
            'course_id' => $course->id,
            'course_code' => $course->code,
            'course_title' => $course->title,
            'dispute_id' => $this->dispute->id,
            'status' => $this->dispute->status->value,
            'resolution_notes' => $this->dispute->resolution_notes,
        ];
    }
}
