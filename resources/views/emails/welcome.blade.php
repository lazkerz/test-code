<x-mail::message>
# Welcome, {{ $user->name }}!

Your account has been created with the email {{ $user->email }}.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
