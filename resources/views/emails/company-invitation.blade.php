<x-mail::message>
# You've been invited!

**{{ $inviterName }}** has invited you to join **{{ $companyName }}** as **{{ $roleName }}**.

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

If you don't want to join, you can <a href="{{ $declineUrl }}">decline this invitation</a>.

This invitation will expire in 7 days.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
