<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ in_array(\Illuminate\Support\Str::before(str_replace('-', '_', $locale), '_'), config('invoiceshelf.rtl_languages', []), true) ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <title>{{ $t('login.sign_in_with_sso') }} · InvoiceShelf</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
    <link rel="shortcut icon" href="/favicons/favicon.ico">
    @foreach ($stylesheets as $stylesheet)
        <link rel="stylesheet" href="{{ $stylesheet }}">
    @endforeach

    <script>
        (function () {
            var theme = localStorage.getItem('theme') || 'system';
            if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>

{{-- The mobile app's sign-in with SSO stops here first (SsoController::app). The device name is the app's choice, so it is only ever printed escaped. --}}
<body class="min-h-screen bg-surface-tertiary bg-glass-gradient font-base theme-{{ $theme }}">
    <main class="relative flex min-h-screen flex-col items-center justify-center px-4 py-12 sm:px-6">
        <div class="mb-8 flex items-center justify-center gap-3">
            <img src="/favicons/android-chrome-192x192.png" alt="" class="h-10 w-10 rounded-lg">
            <span class="text-xl font-semibold text-heading">InvoiceShelf</span>
        </div>

        <article class="w-full max-w-md rounded-xl border border-line-default bg-surface px-6 py-8 shadow-sm sm:px-10 sm:py-10">
            <header class="mb-6 text-center">
                <h1 class="text-2xl font-semibold text-heading break-words">
                    {{ $t('login.sso_app_title', ['device' => $deviceName]) }}
                </h1>
            </header>

            <div class="mb-6 rounded-lg bg-alert-warning-bg px-4 py-3 text-sm text-alert-warning-text break-words">
                {{ $t('login.sso_app_notice') }}
            </div>

            <form id="cancel-form" method="POST" action="{{ route('sso.app.cancel', ['handoff' => $handoff]) }}">
                @csrf
                @method('DELETE')
            </form>

            <form method="POST" action="{{ route('sso.app.continue', ['handoff' => $handoff]) }}">
                @csrf

                <div class="flex flex-col-reverse gap-3 sm:flex-row">
                    <button type="submit" form="cancel-form"
                        class="flex-1 rounded-lg border border-line-default px-4 py-2.5 text-sm font-medium text-body hover:bg-hover">
                        {{ $t('login.sso_app_cancel') }}
                    </button>
                    <button type="submit"
                        class="flex-1 rounded-lg bg-btn-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-btn-primary-hover">
                        {{ $t('login.sso_app_continue') }}
                    </button>
                </div>
            </form>
        </article>
    </main>
</body>

</html>
