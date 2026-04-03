<x-mail::message>
# You've been invited!

**{{ $inviterName }}** has invited you to join **{{ $companyName }}** as **{{ $roleName }}**.

@if($hasAccount)
Log in to accept the invitation:
@else
Create your account to get started:
@endif

<x-mail::button :url="$acceptUrl">
{{ $hasAccount ? 'Log In & Accept' : 'Create Account & Accept' }}
</x-mail::button>

If you don't want to join, you can <a href="{{ $declineUrl }}">decline this invitation</a>.

This invitation will expire in 7 days.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
