<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">

<head>
    <meta charset="utf-8">
    <title>{{ $t('mcp.consent.page_title') }} · InvoiceShelf</title>
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

<body class="min-h-screen bg-surface-tertiary bg-glass-gradient font-base theme-{{ $theme }}">
    <main class="relative flex min-h-screen flex-col items-center justify-center px-4 py-12 sm:px-6">
        <div class="mb-8 flex items-center justify-center gap-3">
            <img src="/favicons/android-chrome-192x192.png" alt="" class="h-10 w-10 rounded-lg">
            <span class="text-xl font-semibold text-heading">InvoiceShelf</span>
        </div>

        <article class="w-full max-w-md rounded-xl border border-line-default bg-surface px-6 py-8 shadow-sm sm:px-10 sm:py-10">
            <header class="mb-6 text-center">
                <h1 class="text-2xl font-semibold text-heading break-words">
                    {{ $t('mcp.consent.title', ['client' => $clientName]) }}
                </h1>
                <p class="mt-2 text-sm text-muted break-words">
                    {{ $t('mcp.consent.subtitle', ['client' => $clientName]) }}
                </p>
            </header>

            <div class="mb-6 rounded-lg bg-alert-warning-bg px-4 py-3 text-sm text-alert-warning-text break-words">
                {{-- Both parts are escaped before the host is set in bold. --}}
                {!! str_replace('{host}', '<strong class="font-semibold break-words">'.e($redirectHost).'</strong>', e($t('mcp.consent.redirect_notice'))) !!}
            </div>

            <p class="mb-6 text-center text-xs text-subtle break-all">
                {{ $t('mcp.consent.signed_in_as', ['email' => $email]) }}
            </p>

            @if ($errors->any())
                <div class="mb-6 rounded-lg bg-alert-error-bg px-4 py-3 text-sm text-alert-error-text" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form id="deny-form" method="POST" action="{{ route('passport.authorizations.deny') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $state }}">
                <input type="hidden" name="client_id" value="{{ $clientId }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">
            </form>

            @if ($companies->isEmpty())
                <p class="mb-6 text-sm text-body">{{ $t('mcp.consent.no_company') }}</p>

                <button type="submit" form="deny-form"
                    class="w-full rounded-lg border border-line-default px-4 py-2.5 text-sm font-medium text-body hover:bg-hover">
                    {{ $t('mcp.consent.deny') }}
                </button>
            @else
                <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="space-y-6">
                    @csrf
                    <input type="hidden" name="state" value="{{ $state }}">
                    <input type="hidden" name="client_id" value="{{ $clientId }}">
                    <input type="hidden" name="auth_token" value="{{ $authToken }}">

                    <div>
                        <label for="company_id" class="mb-1.5 block text-sm font-medium text-heading">
                            {{ $t('mcp.consent.company') }}
                        </label>
                        @if ($companies->count() === 1)
                            <input type="hidden" name="company_id" value="{{ $companies->first()->id }}">
                            <p id="company_id" class="rounded-lg border border-line-default bg-surface-secondary px-3 py-2 text-sm text-heading">
                                {{ $companies->first()->name }}
                            </p>
                        @else
                            <select id="company_id" name="company_id"
                                class="w-full rounded-lg border border-line-default bg-surface px-3 py-2 text-sm text-heading focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}" @selected((int) $selectedCompany === $company->id)>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                        <p class="mt-1.5 text-xs text-muted">{{ $t('mcp.consent.company_help') }}</p>
                    </div>

                    <fieldset>
                        <legend class="mb-1.5 block text-sm font-medium text-heading">{{ $t('mcp.consent.access') }}</legend>
                        <div class="space-y-2">
                            @foreach (['read', 'write'] as $level)
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-line-default px-4 py-3 hover:bg-hover has-[:checked]:border-primary-500 has-[:checked]:ring-1 has-[:checked]:ring-primary-500">
                                    <input type="radio" name="access" value="{{ $level }}" class="mt-1 accent-primary-500"
                                        @checked($selectedAccess === $level)>
                                    <span>
                                        <span class="block text-sm font-medium text-heading">{{ $t('mcp.consent.access_'.$level) }}</span>
                                        <span class="block text-xs text-muted">{{ $t('mcp.consent.access_'.$level.'_help') }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1.5 text-xs text-muted">{{ $t('mcp.consent.role_notice') }}</p>
                    </fieldset>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row">
                        <button type="submit" form="deny-form"
                            class="flex-1 rounded-lg border border-line-default px-4 py-2.5 text-sm font-medium text-body hover:bg-hover">
                            {{ $t('mcp.consent.deny') }}
                        </button>
                        <button type="submit"
                            class="flex-1 rounded-lg bg-btn-primary px-4 py-2.5 text-sm font-medium text-white hover:bg-btn-primary-hover">
                            {{ $t('mcp.consent.allow') }}
                        </button>
                    </div>
                </form>
            @endif
        </article>
    </main>
</body>

</html>
