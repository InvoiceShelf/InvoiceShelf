@component('mail::layout')
    {{-- Header --}}
    @slot('header')
        @component('mail::header', ['url' => ''])
            {{ $companyName }}
        @endcomponent
    @endslot

{{-- Body: Markdown, so these lines must not be indented --}}
{{ __('Hello,') }}

{{ $intro }}

@component('mail::button', ['url' => $url])
{{ $button }}
@endcomponent

@if($expiresOn)
{{ __('This link works until :date.', ['date' => $expiresOn]) }}
@endif

{{ __('If you did not ask for this, you can ignore this email.') }}

    {{-- Footer --}}
    @slot('footer')
        @component('mail::footer')
            Powered by <a class="footer-link" href="https://invoiceshelf.com" target="_blank">InvoiceShelf</a>
        @endcomponent
    @endslot
@endcomponent
