<x-mail::message>
# Welcome to {{ config('app.name') }}

Hi {{ $user->name }},

An administrator has provisioned an account for you on **{{ config('app.name') }}**@if ($roleLabel) as a **{{ $roleLabel }}**@endif.

Sign in with your email address: **{{ $user->email }}**

Use the secure link below to set your password and activate your account. The link expires in {{ $expireHours }} hours and works only once.

<x-mail::button :url="$setupUrl">
Set your password
</x-mail::button>

If the button does not open, copy and paste this URL into your browser:

{{ $setupUrl }}

If you were not expecting this invitation, you can safely ignore this email — your account stays inactive until the link is used. If you have questions, reply to this email to reach the administrator.

Thanks,<br>
The {{ config('app.name') }} team
</x-mail::message>
