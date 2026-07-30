<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default server
    |--------------------------------------------------------------------------
    |
    | Used when no server_id is supplied by the client and no market server
    | connection row exists yet.
    |
    */
    'default_server_id' => env('MARKET_DEFAULT_SERVER_ID', 'ru'),

    /*
    |--------------------------------------------------------------------------
    | Offer lifetime
    |--------------------------------------------------------------------------
    |
    | A market offer is considered "active" for this many hours after it was
    | created in game. Previously hard-coded as a magic `subHours(6)` literal
    | in several query call sites.
    |
    */
    'offer_lifetime_hours' => (int) env('MARKET_OFFER_LIFETIME_HOURS', 6),

    /*
    |--------------------------------------------------------------------------
    | Application cache TTLs (seconds)
    |--------------------------------------------------------------------------
    */
    'cache_ttl' => [
        'public_servers' => 300,
        'goods' => 1800,
        'targets' => 1800,
        'popular' => 900,
        'analytics_overview' => 300,
        'analytics_pair' => 900,
        'arbitrage' => 900,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Cache & Fetch Strategy ('bulk' | 'individual')
    |--------------------------------------------------------------------------
    */
    'cache_strategy' => env('MARKET_CACHE_STRATEGY', 'bulk'),

    /*
    |--------------------------------------------------------------------------
    | Bulk endpoint
    |--------------------------------------------------------------------------
    */
    'bulk' => [
        'min_ttl_seconds' => 30,
        'periods' => ['1d', '7d'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Synchronisation
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'default_interval_minutes' => 15,
        'allowed_intervals' => ['5', '15', '30', '60', 'custom'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */
    'display_name_suffix' => 'Settlers Market',

    /*
    |--------------------------------------------------------------------------
    | Analytics limits
    |--------------------------------------------------------------------------
    */
    'popular_items_limit' => 10,

    'offers_page_size' => 100,

    'max_offers_page_size' => 500,

    /*
    |--------------------------------------------------------------------------
    | Arbitrage detection
    |--------------------------------------------------------------------------
    */
    'arbitrage' => [
        'enable_three_step_loops' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Server connection presets
    |--------------------------------------------------------------------------
    |
    | Moved out of the controller so the presentation layer no longer owns
    | protocol/region knowledge.
    |
    */
    'presets' => [
        ['server_id' => 'ru', 'locale' => 'RU', 'display_name' => 'RU Settlers Market'],
        ['server_id' => 'de', 'locale' => 'DE', 'display_name' => 'DE Settlers Market'],
        ['server_id' => 'en', 'locale' => 'EN', 'display_name' => 'EN Settlers Market'],
        ['server_id' => 'us', 'locale' => 'EN', 'display_name' => 'US Settlers Market'],
        ['server_id' => 'fr', 'locale' => 'FR', 'display_name' => 'FR Settlers Market'],
        ['server_id' => 'pl', 'locale' => 'PL', 'display_name' => 'PL Settlers Market'],
        ['server_id' => 'es', 'locale' => 'ES', 'display_name' => 'ES Settlers Market'],
    ],
];
