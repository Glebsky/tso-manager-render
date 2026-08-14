<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('public-market', static function (Request $request): Limit {
            return Limit::perMinute(120)->by((string) ($request->ip() ?: 'global'));
        });

        RateLimiter::for('api-actions', static function (Request $request): Limit {
            return Limit::perMinute(30)->by((string) ($request->user()?->id ?: $request->ip() ?: 'global'));
        });
    }
}
