<x-mail::message>
# {{ $customerName }}

Your recurring invoice for **{{ $customerName }}** created invoice {{ $number }} for {{ $date }}, for {{ $amount }}.

@if($sent)
It was emailed to the customer.
@else
It was saved as a draft. Send it when it is ready.
@endif

<x-mail::button :url="$url">
View the invoice
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
