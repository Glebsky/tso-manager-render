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
use App\Services\Game\Mines\MinePlacementPolicy;
use App\Services\Game\Mines\ZoneSnapshot;
use App\Services\Tasks\Handlers\BuildMineHandler;
use App\Services\ZoneParserService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class BuildMineHandlerTest extends TestCase
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
        $handler = $this->app->make(BuildMineHandler::class);

        $this->assertTrue($handler->supports('build_mine'));
        $this->assertFalse($handler->supports('collect_building'));
        $this->assertFalse($handler->supports('upgrade_mine'));
    }

    public function test_it_throws_on_missing_or_invalid_grid(): void
    {
        $handler = $this->app->make(BuildMineHandler::class);

        $this->expectException(InvalidTaskTypeException::class);
        $handler->handle($this->account, ['grid' => 0]);
    }

    public function test_it_builds_a_mine_on_a_discovered_deposit(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000)],
                buildingsByGrid: [],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        /** @var MineCommandGatewayInterface&MockInterface $mockGateway */
        $mockGateway = Mockery::mock(MineCommandGatewayInterface::class);
        $mockGateway->expects('buildMine')
            ->with($this->account, 50, 6431)
            ->andReturn('dummy_amf_ok');

        /** @var ZoneParserService&MockInterface $mockParser */
        $mockParser = Mockery::mock(ZoneParserService::class);
        $mockParser->expects('parse')
            ->with('dummy_amf_ok')
            ->andReturn(['errorCode' => 0]);

        $policy = $this->app->make(MinePlacementPolicy::class);
        $handler = new BuildMineHandler($mockZones, $policy, $mockGateway, $mockParser);

        $result = $handler->handle($this->account, ['grid' => 6431]);

        $this->assertStringContainsString('IronMine', $result);
        $this->assertStringContainsString('6431', $result);
    }

    public function test_it_does_not_call_the_gateway_when_the_grid_is_occupied(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000)],
                buildingsByGrid: [6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 1, isProductionActive: true, upgradeInProgress: false)],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        /** @var MineCommandGatewayInterface&MockInterface $mockGateway */
        $mockGateway = Mockery::mock(MineCommandGatewayInterface::class);
        $mockGateway->shouldNotReceive('buildMine');

        /** @var ZoneParserService&MockInterface $mockParser */
        $mockParser = Mockery::mock(ZoneParserService::class);

        $policy = $this->app->make(MinePlacementPolicy::class);
        $handler = new BuildMineHandler($mockZones, $policy, $mockGateway, $mockParser);

        $this->expectException(TaskExecutionException::class);
        $handler->handle($this->account, ['grid' => 6431]);
    }

    public function test_it_falls_back_to_available_deposit_of_same_type_when_requested_grid_is_occupied(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [
                    6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000),
                    7000 => new DepositSnapshot(grid: 7000, name: 'IronOre', amount: 800, maxAmount: 1000),
                ],
                buildingsByGrid: [
                    6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 1, isProductionActive: true, upgradeInProgress: false),
                ],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        /** @var MineCommandGatewayInterface&MockInterface $mockGateway */
        $mockGateway = Mockery::mock(MineCommandGatewayInterface::class);
        $mockGateway->expects('buildMine')
            ->with($this->account, 50, 7000)
            ->andReturn('dummy_amf_ok');

        /** @var ZoneParserService&MockInterface $mockParser */
        $mockParser = Mockery::mock(ZoneParserService::class);
        $mockParser->expects('parse')
            ->with('dummy_amf_ok')
            ->andReturn(['errorCode' => 0]);

        $policy = $this->app->make(MinePlacementPolicy::class);
        $handler = new BuildMineHandler($mockZones, $policy, $mockGateway, $mockParser);

        $result = $handler->handle($this->account, [
            'grid' => 6431,
            'deposit_name' => 'IronOre',
            'mine_name' => 'IronMine',
        ]);

        $this->assertStringContainsString('IronMine', $result);
        $this->assertStringContainsString('7000', $result);
    }

    public function test_it_does_not_call_the_gateway_for_unknown_deposit_type(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'Stone', amount: 1000, maxAmount: 1000)],
                buildingsByGrid: [],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        /** @var MineCommandGatewayInterface&MockInterface $mockGateway */
        $mockGateway = Mockery::mock(MineCommandGatewayInterface::class);
        $mockGateway->shouldNotReceive('buildMine');

        /** @var ZoneParserService&MockInterface $mockParser */
        $mockParser = Mockery::mock(ZoneParserService::class);

        $policy = $this->app->make(MinePlacementPolicy::class);
        $handler = new BuildMineHandler($mockZones, $policy, $mockGateway, $mockParser);

        $this->expectException(TaskExecutionException::class);
        $handler->handle($this->account, ['grid' => 6431]);
    }

    public function test_it_does_not_call_the_gateway_when_the_build_queue_is_full(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000)],
                buildingsByGrid: [],
                buildQueue: new BuildQueueSnapshot(used: 3, total: 3),
            ));

        /** @var MineCommandGatewayInterface&MockInterface $mockGateway */
        $mockGateway = Mockery::mock(MineCommandGatewayInterface::class);
        $mockGateway->shouldNotReceive('buildMine');

        /** @var ZoneParserService&MockInterface $mockParser */
        $mockParser = Mockery::mock(ZoneParserService::class);

        $policy = $this->app->make(MinePlacementPolicy::class);
        $handler = new BuildMineHandler($mockZones, $policy, $mockGateway, $mockParser);

        $this->expectException(TaskExecutionException::class);
        $handler->handle($this->account, ['grid' => 6431]);
    }

    public function test_it_does_not_call_the_gateway_when_deposit_type_mismatches_expected(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000)],
                buildingsByGrid: [],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        /** @var MineCommandGatewayInterface&MockInterface $mockGateway */
        $mockGateway = Mockery::mock(MineCommandGatewayInterface::class);
        $mockGateway->shouldNotReceive('buildMine');

        /** @var ZoneParserService&MockInterface $mockParser */
        $mockParser = Mockery::mock(ZoneParserService::class);

        $policy = $this->app->make(MinePlacementPolicy::class);
        $handler = new BuildMineHandler($mockZones, $policy, $mockGateway, $mockParser);

        $this->expectException(TaskExecutionException::class);
        $handler->handle($this->account, ['grid' => 6431, 'deposit_name' => 'GoldOre']);
    }

    public function test_it_handles_session_error_1005_without_throwing_and_returns_unknown_outcome(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000)],
                buildingsByGrid: [],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        /** @var MineCommandGatewayInterface&MockInterface $mockGateway */
        $mockGateway = Mockery::mock(MineCommandGatewayInterface::class);
        $mockGateway->expects('buildMine')
            ->with($this->account, 50, 6431)
            ->andReturn('dummy_amf_1005');

        /** @var ZoneParserService&MockInterface $mockParser */
        $mockParser = Mockery::mock(ZoneParserService::class);
        $mockParser->expects('parse')
            ->with('dummy_amf_1005')
            ->andReturn(['errorCode' => 1005]);

        $policy = $this->app->make(MinePlacementPolicy::class);
        $handler = new BuildMineHandler($mockZones, $policy, $mockGateway, $mockParser);

        $result = $handler->handle($this->account, ['grid' => 6431]);

        $this->assertStringContainsString('6431', $result);
    }

    public function test_it_handles_session_error_1012_without_throwing_and_returns_unknown_outcome(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000)],
                buildingsByGrid: [],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        /** @var MineCommandGatewayInterface&MockInterface $mockGateway */
        $mockGateway = Mockery::mock(MineCommandGatewayInterface::class);
        $mockGateway->expects('buildMine')
            ->with($this->account, 50, 6431)
            ->andReturn('dummy_amf_1012');

        /** @var ZoneParserService&MockInterface $mockParser */
        $mockParser = Mockery::mock(ZoneParserService::class);
        $mockParser->expects('parse')
            ->with('dummy_amf_1012')
            ->andReturn(['errorCode' => 1012]);

        $policy = $this->app->make(MinePlacementPolicy::class);
        $handler = new BuildMineHandler($mockZones, $policy, $mockGateway, $mockParser);

        $result = $handler->handle($this->account, ['grid' => 6431]);

        $this->assertStringContainsString('6431', $result);
    }

    public function test_it_throws_task_execution_exception_for_other_game_errors(): void
    {
        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->expects('forAccount')
            ->with($this->account)
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000)],
                buildingsByGrid: [],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        /** @var MineCommandGatewayInterface&MockInterface $mockGateway */
        $mockGateway = Mockery::mock(MineCommandGatewayInterface::class);
        $mockGateway->expects('buildMine')
            ->with($this->account, 50, 6431)
            ->andReturn('dummy_amf_error');

        /** @var ZoneParserService&MockInterface $mockParser */
        $mockParser = Mockery::mock(ZoneParserService::class);
        $mockParser->expects('parse')
            ->with('dummy_amf_error')
            ->andReturn(['errorCode' => 500]);

        $policy = $this->app->make(MinePlacementPolicy::class);
        $handler = new BuildMineHandler($mockZones, $policy, $mockGateway, $mockParser);

        $this->expectException(TaskExecutionException::class);
        $handler->handle($this->account, ['grid' => 6431]);
    }
}
