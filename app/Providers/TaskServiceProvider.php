<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Tasks\Handlers\ApplyBuffHandler;
use App\Services\Tasks\Handlers\CollectPickupsHandler;
use App\Services\Tasks\Handlers\SendSpecialistHandler;
use App\Services\Tasks\Handlers\StartProductionHandler;
use App\Services\Tasks\Handlers\StopProductionHandler;
use App\Services\Tasks\TaskHandlerRegistry;
use Illuminate\Support\ServiceProvider;

final class TaskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TaskHandlerRegistry::class, function ($app): TaskHandlerRegistry {
            return new TaskHandlerRegistry($app, [
                StopProductionHandler::class,
                StartProductionHandler::class,
                ApplyBuffHandler::class,
                SendSpecialistHandler::class,
                CollectPickupsHandler::class,
            ]);
        });
    }

    public function boot(): void {}
}
