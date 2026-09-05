<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Amf\Transport\HttpTsoClient;
use App\Services\Amf\Transport\TsoClientInterface;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

final class TsoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TsoAuthService::class);
        $this->app->singleton(TsoClientInterface::class, HttpTsoClient::class);
        $this->app->singleton(TsoAmfService::class);
    }

    public function boot(): void
    {
        $this->warnAboutNonSharedCache();
    }

    /**
     * The game session and its lock are shared through the cache. A per-process
     * cache store (file/array) means every process creates its own game session,
     * which the game server reports as error 1012 to whoever is not the newest
     * owner. Warn loudly instead of failing silently.
     */
    private function warnAboutNonSharedCache(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        $store = (string) config('cache.default');

        if (in_array($store, ['array', 'file'], true) && config('game.scheduler_mode') !== 'sync') {
            Log::warning(
                "[TsoConfig] Cache store '{$store}' is not shared between processes; "
                .'game sessions cannot be reused across web/worker/scheduler. Use redis.'
            );
        }
    }
}
