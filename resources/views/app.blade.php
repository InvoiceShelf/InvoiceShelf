<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <title>{{ get_page_title(!Request::header('company')) }}</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicons/favicon-16x16.png">
    <link rel="manifest" href="/favicons/site.webmanifest">
    <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#5851d8">
    <link rel="shortcut icon" href="/favicons/favicon.ico">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-config" content="/favicons/browserconfig.xml">
    <meta name="theme-color" content="#ffffff">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Module Styles -->
    @foreach(\App\Services\Module\ModuleFacade::allStyles() as $name => $path)
        <link rel="stylesheet" href="/modules/styles/{{ $name }}">
    @endforeach

    @vite('resources/scripts/main.js')
</head>

<body
    class="h-full overflow-hidden bg-gray-100 font-base
    @if(isset($current_theme)) theme-{{ $current_theme }} @else theme-{{get_app_setting('admin_portal_theme') ?? 'invoiceshelf'}} @endif ">

    <!-- Module Scripts -->
    @foreach (\App\Services\Module\ModuleFacade::allScripts() as $name => $path)
        @if (\Illuminate\Support\Str::startsWith($path, ['http://', 'https://']))
            <script type="module" src="{!! $path !!}"></script>
        @else
            <script type="module" src="/modules/scripts/{{ $name }}"></script>
        @endif
    @endforeach

    <script type="module">
        @if(isset($customer_logo))

        window.customer_logo = "/storage/{{$customer_logo}}"

        @endif
        @if(isset($login_page_logo))

        window.login_page_logo = "/storage/{{$login_page_logo}}"

        @endif
        @if(isset($login_page_heading))

        window.login_page_heading = "{{$login_page_heading}}"

        @endif
        @if(isset($login_page_description))

        window.login_page_description = "{{$login_page_description}}"

        @endif
        @if(isset($copyright_text))

        window.copyright_text = "{{$copyright_text}}"

        @endif

        window.InvoiceShelf.start()
    </script>
</body>

</html>
