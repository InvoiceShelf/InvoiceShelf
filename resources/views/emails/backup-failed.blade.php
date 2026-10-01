<x-mail::message>
# Backup failed

Your **{{ $option }}** backup could not be completed:

> {{ $reason }}

No backup file was kept for this run. Check the backup disk and try again.

<x-mail::button :url="$url">
Open backups
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
