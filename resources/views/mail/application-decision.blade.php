<x-mail::message>
# Application decision: {{ $decisionLabel }}

Hi {{ $application->first_name }} {{ $application->last_name }},

@if ($decision === \App\Enums\ApplicationStatus::Admitted)
Congratulations — your application has been **admitted**. You are now enrolled as a student of {{ config('app.name') }}.

@if ($matricule)
Your student matricule is **{{ $matricule }}**. Keep it safe — it identifies you across campus services and you can use it to sign in alongside your email address.
@endif

You can sign in to view your enrollment details on your student dashboard.
@elseif ($decision === \App\Enums\ApplicationStatus::Rejected)
We are sorry to inform you that your application has **not been admitted**.

@if ($application->decision_notes)
Remarks from the admission office:

> {{ $application->decision_notes }}
@endif

You may submit a new application in a future admission window.
@elseif ($decision === \App\Enums\ApplicationStatus::Waitlisted)
Your application has been placed on the **waiting list**. You will be notified as soon as a final decision is taken — no action is required from you at this time.

@if ($application->decision_notes)
Remarks from the admission office:

> {{ $application->decision_notes }}
@endif
@elseif ($decision === \App\Enums\ApplicationStatus::Withdrawn)
The admission office matched your application to your **existing student record** and has restored your prior enrollment instead of creating a new one.

@if ($matricule)
Your matricule remains **{{ $matricule }}** — sign in with it (or your email address) to view your enrollment.
@endif
@endif

If you believe this decision was made in error, reply to this email to reach the student affairs office.

Thanks,<br>
The {{ config('app.name') }} team
</x-mail::message>
