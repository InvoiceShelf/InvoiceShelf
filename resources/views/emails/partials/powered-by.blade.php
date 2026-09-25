@if ($poweredBy = \App\Support\PoweredBy::clientState())
Powered by <a class="footer-link" href="{{ $poweredBy['url'] }}" target="_blank">{{ $poweredBy['name'] }}</a>
@endif
