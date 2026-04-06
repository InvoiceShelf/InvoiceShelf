{{-- Bundled Noto Sans — covers Latin, Cyrillic, Greek, Arabic, Thai, Hindi, and most scripts --}}
<style type="text/css">
    @font-face {
        font-family: 'NotoSans';
        font-style: normal;
        font-weight: normal;
        src: url("{{ resource_path('static/fonts/NotoSans-Regular.ttf') }}") format('truetype');
    }

    @font-face {
        font-family: 'NotoSans';
        font-style: normal;
        font-weight: bold;
        src: url("{{ resource_path('static/fonts/NotoSans-Bold.ttf') }}") format('truetype');
    }

    @font-face {
        font-family: 'NotoSans';
        font-style: italic;
        font-weight: normal;
        src: url("{{ resource_path('static/fonts/NotoSans-Italic.ttf') }}") format('truetype');
    }

    @font-face {
        font-family: 'NotoSans';
        font-style: italic;
        font-weight: bold;
        src: url("{{ resource_path('static/fonts/NotoSans-BoldItalic.ttf') }}") format('truetype');
    }

    {{-- On-demand CJK @font-face rules (only emitted for installed packages). --}}
    {!! implode("\n", app(\App\Services\FontService::class)->getInstalledFontFaces()) !!}

    body {
        font-family: {!! app(\App\Services\FontService::class)->getFontFamilyChain() !!};
    }
</style>
