<x-mail::message>
# {{ $course->code }} session {{ $type === \App\Enums\SessionChangeType::Cancelled ? 'cancelled' : 'rescheduled' }}

Hi {{ $notifiable->name ?? 'there' }},

@if ($type === \App\Enums\SessionChangeType::Cancelled)
Your **{{ $course->code }} — {{ $course->title }}** session scheduled for **{{ $session->scheduled_for->format('l, j M Y \a\t H:i') }}** has been **cancelled**.

@if ($reason)
Reason from the lecturer:

> {{ $reason }}
@endif

@else
Your **{{ $course->code }} — {{ $course->title }}** session has been **moved**.

@if ($previousScheduledFor)
- **From:** {{ $previousScheduledFor->format('l, j M Y \a\t H:i') }}
@endif
- **To:** {{ $session->scheduled_for->format('l, j M Y \a\t H:i') }}
@endif

Sign in to your student dashboard for the latest course schedule.

Thanks,<br>
The {{ config('app.name') }} team
</x-mail::message>
