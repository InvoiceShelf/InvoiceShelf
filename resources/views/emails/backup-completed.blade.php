<x-mail::message>
# Backup completed

Your **{{ $option }}** backup was written to the **{{ $diskName }}** disk as:

> {{ $filename }}

You can download or delete it from the backups page.

<x-mail::button :url="$url">
Open backups
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
