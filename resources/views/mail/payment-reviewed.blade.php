<x-mail::message>
# Payment {{ $status === \App\Enums\PaymentStatus::Validated ? 'validated' : 'rejected' }}

Hi {{ $studentName }},

@if ($status === \App\Enums\PaymentStatus::Validated)
Your reported deposit of **{{ number_format($submission->amount_xaf) }} XAF** to {{ $submission->bank->label() }} (reference {{ $submission->bank_reference }}) has been **validated** by the accounts office.

Sign in to your student dashboard to view your payment record and running balance.
@else
Your reported deposit of **{{ number_format($submission->amount_xaf) }} XAF** to {{ $submission->bank->label() }} (reference {{ $submission->bank_reference }}) has been **rejected**.

@if ($submission->rejection_reason)
Reason from the accounts office:

> {{ $submission->rejection_reason }}
@endif

Please review your bank slip and submit a corrected payment record.
@endif

If you believe this is an error, reply to this email to reach the accounts office.

Thanks,<br>
The {{ config('app.name') }} team
</x-mail::message>
