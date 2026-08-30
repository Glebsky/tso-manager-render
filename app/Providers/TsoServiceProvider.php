<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Amf\Transport\HttpTsoClient;
use App\Services\Amf\Transport\TsoClientInterface;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use Illuminate\Support\ServiceProvider;

final class TsoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TsoAuthService::class);
        $this->app->singleton(TsoClientInterface::class, HttpTsoClient::class);
        $this->app->singleton(TsoAmfService::class);
    }

    public function boot(): void {}
}
