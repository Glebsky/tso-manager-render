<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Exceptions\InvalidTaskTypeException;
use App\Exceptions\TaskExecutionException;
use App\Models\Account;
use App\Services\Game\Mines\BuildingSnapshot;
use App\Services\Game\Mines\BuildQueueSnapshot;
use App\Services\Game\Mines\Contracts\MineCommandGatewayInterface;
use App\Services\Game\Mines\Contracts\ZoneSnapshotProviderInterface;
use App\Services\Game\Mines\DepositSnapshot;
use App\Services\Game\Mines\MineUpgradePolicy;
use App\Services\Game\Mines\ZoneSnapshot;
use App\Services\Tasks\Handlers\UpgradeMineHandler;
use App\Services\ZoneParserService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class UpgradeMineHandlerTest extends TestCase
{
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = new Account([
            'username' => 'test_user',
            'password' => 'secret',
            'server' => 'ru_1',
            'region' => 'ru',
        ]);
        $this->account->id = 1;
    }

    public function test_it_supports_only_its_own_task_type(): void
    {
        $handler = $this->app->make(UpgradeMineHandler::class);

        $this->assertTrue($handler->supports('upgrade_mine'));
        $this->assertFalse($handler->supports('build_mine'));
        $this->assertFalse($handler->supports('collect_building'));
    }

    public function test_it_throws_on_missing_or_invalid_grid(): void
    {
        $handler = $this->app->make(UpgradeMineHandler::class);

        $this->expectException(InvalidTaskTypeException::class);
        $handler->handle($this->account, ['grid' => 0]);
    }

    public function test_it_upgrades_a_mine(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [],
                buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 3, isProductionActive: true, upgradeInProgress: false)],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        /** @var MineCommandGatewayInterface&MockInterface $mockGateway */
        $mockGateway = Mockery::mock(MineCommandGatewayInterface::class);
        $mockGateway->expects('upgradeMine')
            ->with($this->account, 6431)
            ->andReturn('dummy_amf_ok');

        /** @var ZoneParserService&MockInterface $mockParser */
        $mockParser = Mockery::mock(ZoneParserService::class);
        $mockParser->expects('parse')
            ->with('dummy_amf_ok')
            ->andReturn(['errorCode' => 0]);

        $policy = $this->app->make(MineUpgradePolicy::class);
        $handler = new UpgradeMineHandler($mockZones, $policy, $mockGateway, $mockParser);

        $result = $handler->handle($this->account, ['grid' => 6431]);

        $this->assertStringContainsString('IronMine', $result);
        $this->assertStringContainsString('4', $result);
        $this->assertStringContainsString('6431', $result);
    }

    public function test_it_does_not_call_the_gateway_for_a_non_mine_building(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [],
                buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'Woodcutter', upgradeLevel: 2, isProductionActive: true, upgradeInProgress: false)],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        /** @var MineCommandGatewayInterface&MockInterface $mockGateway */
        $mockGateway = Mockery::mock(MineCommandGatewayInterface::class);
        $mockGateway->shouldNotReceive('upgradeMine');

        /** @var ZoneParserService&MockInterface $mockParser */
        $mockParser = Mockery::mock(ZoneParserService::class);

        $policy = $this->app->make(MineUpgradePolicy::class);
        $handler = new UpgradeMineHandler($mockZones, $policy, $mockGateway, $mockParser);

        $this->expectException(TaskExecutionException::class);
        $handler->handle($this->account, ['grid' => 6431]);
    }

    public function test_it_does_not_call_the_gateway_at_max_level(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [],
                buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 7, isProductionActive: true, upgradeInProgress: false)],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        /** @var MineCommandGatewayInterface&MockInterface $mockGateway */
        $mockGateway = Mockery::mock(MineCommandGatewayInterface::class);
        $mockGateway->shouldNotReceive('upgradeMine');

        /** @var ZoneParserService&MockInterface $mockParser */
        $mockParser = Mockery::mock(ZoneParserService::class);

        $policy = $this->app->make(MineUpgradePolicy::class);
        $handler = new UpgradeMineHandler($mockZones, $policy, $mockGateway, $mockParser);

        $this->expectException(TaskExecutionException::class);
        $handler->handle($this->account, ['grid' => 6431]);
    }

    public function test_it_does_not_call_the_gateway_when_upgrade_already_in_progress(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [],
                buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 3, isProductionActive: true, upgradeInProgress: true)],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        /** @var MineCommandGatewayInterface&MockInterface $mockGateway */
        $mockGateway = Mockery::mock(MineCommandGatewayInterface::class);
        $mockGateway->shouldNotReceive('upgradeMine');

        /** @var ZoneParserService&MockInterface $mockParser */
        $mockParser = Mockery::mock(ZoneParserService::class);

        $policy = $this->app->make(MineUpgradePolicy::class);
        $handler = new UpgradeMineHandler($mockZones, $policy, $mockGateway, $mockParser);

        $this->expectException(TaskExecutionException::class);
        $handler->handle($this->account, ['grid' => 6431]);
    }

    public function test_it_does_not_call_the_gateway_when_mine_is_depleted(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 0, maxAmount: 1000)],
                buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 2, isProductionActive: true, upgradeInProgress: false)],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        /** @var MineCommandGatewayInterface&MockInterface $mockGateway */
        $mockGateway = Mockery::mock(MineCommandGatewayInterface::class);
        $mockGateway->shouldNotReceive('upgradeMine');

        /** @var ZoneParserService&MockInterface $mockParser */
        $mockParser = Mockery::mock(ZoneParserService::class);

        $policy = $this->app->make(MineUpgradePolicy::class);
        $handler = new UpgradeMineHandler($mockZones, $policy, $mockGateway, $mockParser);

        $this->expectException(TaskExecutionException::class);
        $handler->handle($this->account, ['grid' => 6431]);
    }

    public function test_it_handles_session_errors_without_throwing_and_returns_unknown_outcome(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [],
                buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 3, isProductionActive: true, upgradeInProgress: false)],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        /** @var MineCommandGatewayInterface&MockInterface $mockGateway */
        $mockGateway = Mockery::mock(MineCommandGatewayInterface::class);
        $mockGateway->expects('upgradeMine')
            ->with($this->account, 6431)
            ->andReturn('dummy_amf_1012');

        /** @var ZoneParserService&MockInterface $mockParser */
        $mockParser = Mockery::mock(ZoneParserService::class);
        $mockParser->expects('parse')
            ->with('dummy_amf_1012')
            ->andReturn(['errorCode' => 1012]);

        $policy = $this->app->make(MineUpgradePolicy::class);
        $handler = new UpgradeMineHandler($mockZones, $policy, $mockGateway, $mockParser);

        $result = $handler->handle($this->account, ['grid' => 6431]);

        $this->assertStringContainsString('6431', $result);
    }

    public function test_it_throws_task_execution_exception_for_game_errors(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [],
                buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 3, isProductionActive: true, upgradeInProgress: false)],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        /** @var MineCommandGatewayInterface&MockInterface $mockGateway */
        $mockGateway = Mockery::mock(MineCommandGatewayInterface::class);
        $mockGateway->expects('upgradeMine')
            ->with($this->account, 6431)
            ->andReturn('dummy_amf_error');

        /** @var ZoneParserService&MockInterface $mockParser */
        $mockParser = Mockery::mock(ZoneParserService::class);
        $mockParser->expects('parse')
            ->with('dummy_amf_error')
            ->andReturn(['errorCode' => 500]);

        $policy = $this->app->make(MineUpgradePolicy::class);
        $handler = new UpgradeMineHandler($mockZones, $policy, $mockGateway, $mockParser);

        $this->expectException(TaskExecutionException::class);
        $handler->handle($this->account, ['grid' => 6431]);
    }
}
