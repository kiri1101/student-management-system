<x-mail::message>
# Dispute reviewed

Hi {{ $notifiable->name ?? 'there' }},

Your dispute for **{{ $course->code }} — {{ $course->title }}** has been **{{ $status }}**.

@if ($resolutionNotes)
Notes from the reviewer:

> {{ $resolutionNotes }}
@endif

<x-mail::button :url="$url">
View my results
</x-mail::button>

Thanks,<br>
The {{ config('app.name') }} team
</x-mail::message>
