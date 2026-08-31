<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\UnknownTaskActionException;
use App\Services\Tasks\Handlers\ApplyBuffHandler;
use App\Services\Tasks\Handlers\BuildMineHandler;
use App\Services\Tasks\Handlers\CollectBuildingHandler;
use App\Services\Tasks\Handlers\CollectPickupsHandler;
use App\Services\Tasks\Handlers\SendSpecialistHandler;
use App\Services\Tasks\Handlers\StartProductionHandler;
use App\Services\Tasks\Handlers\StopProductionHandler;
use App\Services\Tasks\Handlers\UpgradeMineHandler;
use App\Services\Tasks\TaskHandlerRegistry;
use Tests\TestCase;

class TaskHandlerRegistryTest extends TestCase
{
    private TaskHandlerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->app->make(TaskHandlerRegistry::class);
    }

    public function test_resolves_all_standard_handlers(): void
    {
        $this->assertInstanceOf(StopProductionHandler::class, $this->registry->getHandler('stop_production'));
        $this->assertInstanceOf(StartProductionHandler::class, $this->registry->getHandler('start_production'));
        $this->assertInstanceOf(ApplyBuffHandler::class, $this->registry->getHandler('apply_buff'));
        $this->assertInstanceOf(SendSpecialistHandler::class, $this->registry->getHandler('send_geologist'));
        $this->assertInstanceOf(SendSpecialistHandler::class, $this->registry->getHandler('send_explorer'));
        $this->assertInstanceOf(SendSpecialistHandler::class, $this->registry->getHandler('send_specialist'));
        $this->assertInstanceOf(CollectPickupsHandler::class, $this->registry->getHandler('collect_pickups'));
        $this->assertInstanceOf(CollectBuildingHandler::class, $this->registry->getHandler('collect_building'));
        $this->assertInstanceOf(BuildMineHandler::class, $this->registry->getHandler('build_mine'));
        $this->assertInstanceOf(UpgradeMineHandler::class, $this->registry->getHandler('upgrade_mine'));
    }

    public function test_throws_unknown_task_action_exception_for_invalid_type(): void
    {
        $this->expectException(UnknownTaskActionException::class);

        $this->registry->getHandler('unknown_task_type_xyz');
    }
}
