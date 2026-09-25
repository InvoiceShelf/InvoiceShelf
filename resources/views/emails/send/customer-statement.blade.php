@component('mail::layout')
    @slot('header')
        @component('mail::header', ['url' => ''])
            {{ $data['customer']->company->name }}
        @endcomponent
    @endslot

    @slot('subcopy')
        @component('mail::subcopy')
            {!! $data['body'] !!}
        @endcomponent
    @endslot

    @slot('footer')
        @component('mail::footer')
            @include('emails.partials.powered-by')
        @endcomponent
    @endslot
@endcomponent
