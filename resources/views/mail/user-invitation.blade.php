<x-mail::message>
# Welcome to {{ config('app.name') }}

Hi {{ $user->name }},

An administrator has created an account for you on **{{ config('app.name') }}** with the role **{{ ucfirst($role) }}**.

Your username is your email address: **{{ $user->email }}**

To activate your account, set your password using the secure link below. The link expires in {{ $expireHours }} hours and can only be used once.

<x-mail::button :url="$setupUrl">
Set your password
</x-mail::button>

If you did not expect this email, please contact the administrator. Your account will remain inactive until the link is used.

Thanks,<br>
The {{ config('app.name') }} team
</x-mail::message>
