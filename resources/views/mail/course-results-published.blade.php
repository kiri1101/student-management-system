<x-mail::message>
# {{ $course->code }} results published

Hi {{ $notifiable->name ?? 'there' }},

Your results for **{{ $course->code }} — {{ $course->title }}** are now available.

<x-mail::button :url="$url">
View my results
</x-mail::button>

Thanks,<br>
The {{ config('app.name') }} team
</x-mail::message>
