<x-mail::message>
# {{ $title }}

{{ $body }}

@if($url)
<x-mail::button :url="$url">
{{ $action }}
</x-mail::button>
@endif

{{ config('app.name') }}
@if($preferences)

<small><a href="{{ $preferences }}">{{ $preferencesLabel }}</a></small>
@endif
</x-mail::message>
