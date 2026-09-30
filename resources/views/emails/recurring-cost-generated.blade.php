<x-mail::message>
# {{ $scheduleName }}

Your recurring schedule **{{ $scheduleName }}** created {{ $recordLabel }} for {{ $date }}, for {{ $amount }}.

@if($isDraft)
It was saved as a draft. Check the amount and record it when it is right.
@endif

<x-mail::button :url="$url">
View it
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
