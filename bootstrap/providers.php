<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\MarketServiceProvider;
use App\Providers\RouteServiceProvider;
use App\Providers\TaskServiceProvider;
use App\Providers\TsoServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    EventServiceProvider::class,
    MarketServiceProvider::class,
    RouteServiceProvider::class,
    TaskServiceProvider::class,
    TsoServiceProvider::class,
];
