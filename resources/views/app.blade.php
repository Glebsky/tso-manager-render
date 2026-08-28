<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ __('ui.market.public_subtitle') }}">
    <title>TSO Manager</title>

    <!-- Favicons & Manifest -->
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="shortcut icon" href="/favicon.ico">
    <script>
        window.__APP_LOCALE__ = @json(app()->getLocale());
        window.__AUTH_USER__ = {!! json_encode([
            'id' => auth()->id(),
            'name' => auth()->user()?->name,
            'email' => auth()->user()?->email,
        ]) !!};
    </script>
    @vite(['resources/js/app.js'])
</head>
<body class="h-full bg-dark-950 font-sans text-white antialiased">
    <div id="app" class="h-full"></div>
</body>
</html>
