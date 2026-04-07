{{-- All @font-face rules — bundled and on-demand — are emitted by
     FontService::getInstalledFontFaces(). Bundled packages live under
     resources/static/fonts/, on-demand packages under storage/fonts/. --}}
<style type="text/css">
    {!! implode("\n", app(\App\Services\FontService::class)->getInstalledFontFaces()) !!}

    body {
        font-family: {!! app(\App\Services\FontService::class)->getFontFamilyChain() !!};
    }
</style>
