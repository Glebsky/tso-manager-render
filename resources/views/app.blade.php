@php
    $isPublicMarket = request()->is('/') || !request()->is('admin*');
    $pageTitle = $isPublicMarket
        ? 'TSO Market — The Settlers Online Market & Combat Simulator'
        : 'TSO Manager';
    $metaDescription = $isPublicMarket
        ? 'TSO Market for The Settlers Online — market price history, price analysis and a smart combat simulator that optimizes attacks using your generals.'
        : __('ui.market.public_subtitle');
    $canonicalUrl = 'https://tso-market.vercel.app/';
    $ogImage = 'https://tso-market.vercel.app/android-chrome-512x512.png';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Google Search Console Verification -->
    <meta name="google-site-verification" content="3zfiir-_Gwcyr_0ib9dhYTGhjAuQkaI1yjTE1PKG5oI" />

    <!-- SEO Meta Tags -->
    <title>{!! $pageTitle !!}</title>
    <meta name="description" content="{{ $metaDescription }}">
    @if ($isPublicMarket)
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="{{ $canonicalUrl }}">

        <!-- Open Graph -->
        <meta property="og:type" content="website">
        <meta property="og:title" content="TSO Market — The Settlers Online Market & Combat Simulator">
        <meta property="og:description" content="Track market prices, analyze price history and optimize attacks with your generals in The Settlers Online.">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:site_name" content="TSO Market">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:width" content="512">
        <meta property="og:image:height" content="512">
        <meta property="og:image:alt" content="TSO Market — The Settlers Online Market & Combat Simulator">

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="TSO Market — The Settlers Online Market & Combat Simulator">
        <meta name="twitter:description" content="Track market prices, analyze price history and optimize attacks with your generals in The Settlers Online.">
        <meta name="twitter:image" content="{{ $ogImage }}">

        <!-- Structured Data (Schema.org JSON-LD) -->
        <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebApplication',
            'name' => 'TSO Market',
            'url' => 'https://tso-market.vercel.app/',
            'description' => 'Market price tracker and smart combat simulator for The Settlers Online.',
            'applicationCategory' => 'GameApplication',
            'operatingSystem' => 'Web',
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
        </script>
    @else
        <meta name="robots" content="noindex, nofollow">
    @endif

    <!-- Theme & Color Scheme -->
    <meta name="theme-color" content="#020617">
    <meta name="color-scheme" content="dark">

    <!-- Mobile & PWA Support -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{!! $pageTitle !!}">
    <meta name="msapplication-TileColor" content="#020617">
    <meta name="msapplication-navbutton-color" content="#020617">

    <!-- Favicons & Manifest -->
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="shortcut icon" href="/favicon.ico">

    <script>
        window.__APP_LOCALE__ = @json(app()->getLocale());
        window.__MARKET_CACHE_STRATEGY__ = @json(config('market.cache_strategy', 'bulk'));
        window.__MARKET_COMBAT_SIMULATOR_URL__ = @json(config('market.combat_simulator_url'));
        window.__AUTH_USER__ = {!! json_encode([
            'id' => auth()->id(),
            'name' => auth()->user()?->name,
            'email' => auth()->user()?->email,
        ]) !!};
    </script>
    @vite(['resources/js/app.js'])
</head>
<body class="h-full bg-dark-950 font-sans text-white antialiased">
    <div id="app" class="h-full">
        @if ($isPublicMarket)
            {{-- Crawlable semantic HTML structure for non-JS bots and search indexers --}}
            <div class="sr-only">
                <h1>TSO Market — The Settlers Online Market &amp; Combat Simulator</h1>
                <p>TSO Market is a free tool for The Settlers Online players. Track market prices and price history, analyze item values and make better trading decisions.</p>
                <section>
                    <h2>Market Price Tracker</h2>
                    <p>Real-time trade prices, historical demand and trading analytics for The Settlers Online.</p>
                </section>
                <section>
                    <h2>Smart Combat Simulator</h2>
                    <p>The smart combat simulator helps you build optimized attacks using your available generals. Instead of simply calculating a battle result, it distributes stronger generals to harder camps and weaker generals to easier encounters, helping you plan efficient attacks across an entire adventure.</p>
                </section>
            </div>
            <noscript>
                <div style="padding: 2rem; text-align: center; color: #ffffff; background-color: #020617; font-family: ui-sans-serif, system-ui, sans-serif;">
                    <h1>TSO Market — The Settlers Online Market &amp; Combat Simulator</h1>
                    <p>TSO Market is a free tool for The Settlers Online players. Track market prices and price history, analyze item values and make better trading decisions.</p>
                    <p style="margin-top: 1rem; color: #94a3b8;">Please enable JavaScript to use the interactive TSO Market portal.</p>
                </div>
            </noscript>
        @endif
    </div>
</body>
</html>
