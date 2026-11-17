<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $company->name }}</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #f3f4f6;
            --card: #ffffff;
            --text: #111827;
            --muted: #4b5563;
            --border: #e5e7eb;
            --accent: #5851d8;
            --accent-text: #ffffff;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #111827;
                --card: #1f2937;
                --text: #f9fafb;
                --muted: #d1d5db;
                --border: #374151;
                --accent: #8f8af0;
                --accent-text: #111827;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }

        main {
            width: 100%;
            max-width: 28rem;
            padding: 32px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        .company {
            margin-bottom: 20px;
            color: var(--muted);
            font-size: .8125rem;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
        }

        h1 { margin: 0 0 12px; font-size: 1.25rem; }

        p { margin: 0 0 16px; color: var(--muted); line-height: 1.5; }

        button {
            width: 100%;
            padding: 12px 16px;
            border: 0;
            border-radius: 8px;
            background: var(--accent);
            color: var(--accent-text);
            font: inherit;
            font-weight: 600;
            cursor: pointer;
        }

        button:focus-visible { outline: 3px solid var(--text); outline-offset: 2px; }
    </style>
</head>
<body>
<main>
    <div class="company">{{ $company->name }}</div>

    @switch($state)
        @case('offer')
            <h1>{{ __('This link has been replaced') }}</h1>
            <p>{{ __('For your security, links in emails sent before a recent update no longer open documents directly.') }}</p>
            <p>{{ __('We can email a new link to the address this message was sent to.') }}</p>
            <form method="POST">
                @csrf
                <button type="submit">{{ __('Email me a new link') }}</button>
            </form>
            @break

        @case('sent')
            <h1>{{ __('Check your inbox') }}</h1>
            <p>{{ __('A new link is on its way to the address this message was sent to. It can take a few minutes to arrive.') }}</p>
            @break

        @case('expired')
            <h1>{{ __('This link has expired') }}</h1>
            <p>{{ __('Please contact :company for a new copy.', ['company' => $company->name]) }}</p>
            @break

        @case('limited')
            <h1>{{ __('Too many requests') }}</h1>
            <p>{{ __('Please try again later, or contact :company.', ['company' => $company->name]) }}</p>
            @break

        @case('unavailable')
            <h1>{{ __("We can't send a new link for this message") }}</h1>
            <p>{{ __('Please contact :company for a new copy.', ['company' => $company->name]) }}</p>
            @break

        @default
            <h1>{{ __("We couldn't send a new link") }}</h1>
            <p>{{ __('Please try again later, or contact :company.', ['company' => $company->name]) }}</p>
    @endswitch
</main>
</body>
</html>
