<x-mail::message>
# Deferral request {{ $status === \App\Enums\DeferralStatus::Approved ? 'approved' : 'rejected' }}

Hi {{ $studentName }},

@if ($status === \App\Enums\DeferralStatus::Approved)
Your tuition deferral request has been **approved**. Your access has been extended until **{{ $deferral->new_deadline?->toFormattedDateString() }}**.

@if ($deferral->decision_notes)
Note from the accounts office:

> {{ $deferral->decision_notes }}
@endif

Settle the outstanding balance before that date to keep your access.
@else
Your tuition deferral request has been **rejected**.

@if ($deferral->decision_notes)
Reason from the accounts office:

> {{ $deferral->decision_notes }}
@endif

Please settle your outstanding tuition to regain access, or contact the accounts office.
@endif

Thanks,<br>
The {{ config('app.name') }} team
</x-mail::message>
