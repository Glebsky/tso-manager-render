<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>TSO Manager</title>
    <script>
        window.__AUTH_USER__ = {!! json_encode([
            'id' => auth()->id(),
            'name' => auth()->user()?->name,
            'email' => auth()->user()?->email,
        ]) !!};
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-dark-950 font-sans text-white antialiased">
    <div id="app" class="h-full"></div>
</body>
</html>
