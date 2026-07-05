<x-mail::message>
# Documents requested for your application

Hi {{ $application->first_name }} {{ $application->last_name }},

The admission office has reviewed your application and needs you to replace the following
{{ $rejectedDocuments->count() === 1 ? 'document' : 'documents' }} before the review can continue:

@foreach ($rejectedDocuments as $document)
- **{{ $document->documentType->name }}** ({{ $document->documentType->code }})
@if ($document->review_notes)
  — {{ $document->review_notes }}
@endif
@endforeach

@if ($application->decision_notes)
Remarks from the admission office:

> {{ $application->decision_notes }}
@endif

Sign in and open your application to upload the replacement
{{ $rejectedDocuments->count() === 1 ? 'file' : 'files' }} — once every requested document has
been replaced, your application automatically returns to the review queue.

<x-mail::button :url="$applicationUrl">
View my application
</x-mail::button>

Thanks,<br>
The {{ config('app.name') }} team
</x-mail::message>
