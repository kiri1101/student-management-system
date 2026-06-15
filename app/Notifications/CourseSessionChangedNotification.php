<?php

namespace App\Notifications;

use App\Enums\SessionChangeType;
use App\Models\CourseSession;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourseSessionChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly CourseSession $session,
        public readonly SessionChangeType $type,
        public readonly ?string $reason = null,
        public readonly ?CarbonImmutable $previousScheduledFor = null,
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
        $verb = $this->type === SessionChangeType::Cancelled ? 'cancelled' : 'rescheduled';

        return (new MailMessage)
            ->subject(config('app.name').' — '.$this->session->course->code.' session '.$verb)
            ->markdown('mail.course-session-changed', [
                'session' => $this->session,
                'type' => $this->type,
                'reason' => $this->reason,
                'previousScheduledFor' => $this->previousScheduledFor,
                'course' => $this->session->course,
            ]);
    }

    /**
     * The in-app payload. The student notification UI and tests depend on this
     * exact shape — keep keys and value formats stable.
     *
     * @return array{
     *     type: string,
     *     course_id: int,
     *     course_code: string,
     *     course_title: string,
     *     session_id: int,
     *     scheduled_for: string,
     *     reason: string|null,
     *     previous_scheduled_for: string|null
     * }
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type->value,
            'course_id' => $this->session->course_id,
            'course_code' => $this->session->course->code,
            'course_title' => $this->session->course->title,
            'session_id' => $this->session->id,
            'scheduled_for' => $this->session->scheduled_for->toIso8601String(),
            'reason' => $this->reason,
            'previous_scheduled_for' => $this->previousScheduledFor?->toIso8601String(),
        ];
    }
}
