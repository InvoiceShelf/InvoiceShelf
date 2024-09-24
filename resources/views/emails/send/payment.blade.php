@component('mail::layout')
    {{-- Header --}}
    @slot('header')
        @component('mail::header', ['url' => ''])
        @if($data['company']['logo'])
            <img class="header-logo" src="{{asset($data['company']['logo'])}}" alt="{{$data['company']['name']}}">
        @else
            {{$data['company']['name']}}
        @endif
        @endcomponent
    @endslot

    {{-- Body --}}
    <!-- Body here -->

    {{-- Subcopy --}}
    @slot('subcopy')
        @component('mail::subcopy')
            {!! $data['body'] !!}
            @if(!$data['attach']['data'])
                @component('mail::button', ['url' => $data['url']])
                    @lang('mail_view_payment')
                @endcomponent
            @endif
        @endcomponent
    @endslot

    {{-- Footer --}}
    @slot('footer')
        @component('mail::footer')
            Powered by <a class="footer-link" href="https://invoiceshelf.com" target="_blank">InvoiceShelf</a>
        @endcomponent
    @endslot
@endcomponent
