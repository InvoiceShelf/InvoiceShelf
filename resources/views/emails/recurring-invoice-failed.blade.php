<x-mail::message>
# {{ $customerName }}

@if($sendFailed)
Your recurring invoice for **{{ $customerName }}** created its invoice, but it could not be emailed to the customer:
@else
Your recurring invoice for **{{ $customerName }}** could not create its invoice:
@endif

> {{ $reason }}

@if($sendFailed)
The invoice was kept as a draft. Send it from the invoice once the problem is fixed.
@else
Nothing was created for this run. The schedule tries again every hour until it succeeds, and you will only be emailed again if the problem changes. Fix the schedule to continue.
@endif

<x-mail::button :url="$url">
Open the schedule
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
