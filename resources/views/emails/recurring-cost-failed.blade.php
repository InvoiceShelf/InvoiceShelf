<x-mail::message>
# {{ $scheduleName }}

Your recurring schedule **{{ $scheduleName }}** could not create its bill or expense:

> {{ $reason }}

Nothing was recorded for this run. The schedule tries again every hour until it succeeds, and you will not be emailed again about this problem. Fix the schedule to continue.

<x-mail::button :url="$url">
Open the schedule
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
