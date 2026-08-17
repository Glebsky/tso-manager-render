<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Game\BuildingClickResolver;
use App\Services\Game\ClickableBuildingRegistry;
use App\Services\Tasks\Handlers\ApplyBuffHandler;
use App\Services\Tasks\Handlers\CollectBuildingHandler;
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
        $this->app->singleton(ClickableBuildingRegistry::class, function ($app): ClickableBuildingRegistry {
            /** @var list<array{pattern: string, kind: int}> $patterns */
            $patterns = config('game.collectibles.clickable_patterns', []);

            return new ClickableBuildingRegistry($patterns);
        });

        $this->app->singleton(BuildingClickResolver::class, function ($app): BuildingClickResolver {
            return new BuildingClickResolver($app->make(ClickableBuildingRegistry::class));
        });

        $this->app->bind(TaskHandlerRegistry::class, function ($app): TaskHandlerRegistry {
            return new TaskHandlerRegistry($app, [
                StopProductionHandler::class,
                StartProductionHandler::class,
                ApplyBuffHandler::class,
                SendSpecialistHandler::class,
                CollectPickupsHandler::class,
                CollectBuildingHandler::class,
            ]);
        });
    }

    public function boot(): void {}
}
