<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Game\BuildingClickResolver;
use App\Services\Game\ClickableBuildingRegistry;
use App\Services\Game\Mines\AmfMineCommandGateway;
use App\Services\Game\Mines\AmfZoneSnapshotProvider;
use App\Services\Game\Mines\ConfigMineCatalog;
use App\Services\Game\Mines\Contracts\MineCatalogInterface;
use App\Services\Game\Mines\Contracts\MineCommandGatewayInterface;
use App\Services\Game\Mines\Contracts\ZoneSnapshotProviderInterface;
use App\Services\Tasks\Handlers\ApplyBuffHandler;
use App\Services\Tasks\Handlers\BuildMineHandler;
use App\Services\Tasks\Handlers\CollectBuildingHandler;
use App\Services\Tasks\Handlers\CollectPickupsHandler;
use App\Services\Tasks\Handlers\SendSpecialistHandler;
use App\Services\Tasks\Handlers\StartProductionHandler;
use App\Services\Tasks\Handlers\StopProductionHandler;
use App\Services\Tasks\Handlers\UpgradeMineHandler;
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

        $this->app->singleton(MineCatalogInterface::class, function (): ConfigMineCatalog {
            /** @var array<string, array{mine: string, number: int, max_level: int}> $config */
            $config = (array) config('game.buildings.mines', []);

            return new ConfigMineCatalog($config);
        });

        $this->app->bind(ZoneSnapshotProviderInterface::class, AmfZoneSnapshotProvider::class);
        $this->app->bind(MineCommandGatewayInterface::class, AmfMineCommandGateway::class);

        $this->app->bind(TaskHandlerRegistry::class, function ($app): TaskHandlerRegistry {
            return new TaskHandlerRegistry($app, [
                StopProductionHandler::class,
                StartProductionHandler::class,
                ApplyBuffHandler::class,
                SendSpecialistHandler::class,
                CollectPickupsHandler::class,
                CollectBuildingHandler::class,
                BuildMineHandler::class,
                UpgradeMineHandler::class,
            ]);
        });
    }

    public function boot(): void {}
}
