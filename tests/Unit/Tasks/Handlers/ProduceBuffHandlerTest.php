<?php

declare(strict_types=1);

namespace Tests\Unit\Tasks\Handlers;

use App\Enums\ProductionRejectionReason;
use App\Enums\TaskType;
use App\Exceptions\InvalidTaskTypeException;
use App\Models\Account;
use App\Services\Game\Production\ConfigProductionCatalog;
use App\Services\Game\Production\ProductionCommandGatewayInterface;
use App\Services\Game\Production\ProductionOrderPolicy;
use App\Services\Game\Production\ZoneSnapshotProviderInterface;
use App\Services\Tasks\Handlers\ProduceBuffHandler;
use App\Services\ZoneParserService;
use App\Support\Zone\ZoneSnapshot;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class ProduceBuffHandlerTest extends TestCase
{
    private ZoneSnapshotProviderInterface&MockInterface $zonesMock;

    private ProductionCommandGatewayInterface&MockInterface $gatewayMock;

    private ZoneParserService&MockInterface $zoneParserMock;

    private ConfigProductionCatalog $catalog;

    private ProductionOrderPolicy $policy;

    private ProduceBuffHandler $handler;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zonesMock = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $this->gatewayMock = Mockery::mock(ProductionCommandGatewayInterface::class);
        $this->zoneParserMock = Mockery::mock(ZoneParserService::class);

        $this->catalog = new ConfigProductionCatalog([
            'producers' => [
                'ProvisionHouse' => 1,
            ],
            'recipes' => [
                1 => [
                    [
                        'name' => 'ProductivityBuffLvl3',
                        'group' => 0,
                        'duration_seconds' => 1800,
                        'buff_type' => 'Timed',
                        'requires_upgrade_level_min' => 0,
                        'requires_upgrade_level_max' => 99,
                        'requires_event' => null,
                        'requires_quest' => null,
                        'costs' => [],
                        'costs_known' => true,
                    ],
                ],
            ],
        ]);

        $this->policy = new ProductionOrderPolicy($this->catalog);

        $this->handler = new ProduceBuffHandler(
            $this->zonesMock,
            $this->policy,
            $this->gatewayMock,
            $this->zoneParserMock,
        );

        $this->account = new Account;
        $this->account->id = 1;
        $this->account->dso_auth_user = '1601416';
    }

    public function test_supports_produce_buff(): void
    {
        $this->assertTrue($this->handler->supports(TaskType::ProduceBuff->value));
        $this->assertFalse($this->handler->supports(TaskType::ApplyBuff->value));
        $this->assertFalse($this->handler->supports(TaskType::BuildMine->value));
    }

    public function test_it_executes_successful_order(): void
    {
        $snapshot = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 1234,
                    'buildingName_string' => 'ProvisionHouse',
                    'upgradeLevel' => 2,
                    'upgradeIsInProgress' => false,
                ],
            ],
            'production_queues' => [],
        ]);

        $this->zonesMock->shouldReceive('forAccount')
            ->once()
            ->with($this->account)
            ->andReturn($snapshot);

        $this->gatewayMock->shouldReceive('queueOrder')
            ->once()
            ->with($this->account, 1234, 1, 'ProductivityBuffLvl3', 5, 1)
            ->andReturn('dummy_amf');

        $this->zoneParserMock->shouldReceive('parse')
            ->once()
            ->with('dummy_amf')
            ->andReturn(['errorCode' => 0]);

        $payload = [
            'grid' => 1234,
            'production_type' => 1,
            'recipe_name' => 'ProductivityBuffLvl3',
            'amount' => 5,
            'stacks' => 1,
        ];

        $result = $this->handler->handle($this->account, $payload);

        $this->assertStringContainsString('ProductivityBuffLvl3', $result);
    }

    public function test_it_returns_rejection_reason_when_policy_rejects(): void
    {
        $snapshot = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 1234,
                    'buildingName_string' => 'ProvisionHouse',
                    'upgradeIsInProgress' => true, // Upgrading!
                ],
            ],
            'production_queues' => [],
        ]);

        $this->zonesMock->shouldReceive('forAccount')
            ->once()
            ->with($this->account)
            ->andReturn($snapshot);

        $this->gatewayMock->shouldNotReceive('queueOrder');

        $payload = [
            'grid' => 1234,
            'production_type' => 1,
            'recipe_name' => 'ProductivityBuffLvl3',
            'amount' => 1,
        ];

        $result = $this->handler->handle($this->account, $payload);

        $this->assertSame(
            __('tasks.produce_buff.rejected.'.ProductionRejectionReason::BuildingUpgrading->value, [
                'grid' => 1234,
                'recipe' => 'ProductivityBuffLvl3',
                'type' => 1,
            ]),
            $result
        );
    }

    public function test_it_handles_session_error_code_with_unknown_outcome(): void
    {
        $snapshot = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 1234,
                    'buildingName_string' => 'ProvisionHouse',
                    'upgradeIsInProgress' => false,
                ],
            ],
            'production_queues' => [],
        ]);

        $this->zonesMock->shouldReceive('forAccount')
            ->once()
            ->with($this->account)
            ->andReturn($snapshot);

        $this->gatewayMock->shouldReceive('queueOrder')
            ->once()
            ->andReturn('dummy_amf');

        $this->zoneParserMock->shouldReceive('parse')
            ->once()
            ->andReturn(['errorCode' => 1012]);

        $payload = [
            'grid' => 1234,
            'production_type' => 1,
            'recipe_name' => 'ProductivityBuffLvl3',
            'amount' => 1,
        ];

        $result = $this->handler->handle($this->account, $payload);

        $this->assertSame(
            __('tasks.produce_buff.unknown_outcome', ['grid' => 1234, 'recipe' => 'ProductivityBuffLvl3']),
            $result
        );
    }

    public function test_it_throws_invalid_task_type_exception_on_invalid_parameters(): void
    {
        $this->expectException(InvalidTaskTypeException::class);

        $this->handler->handle($this->account, [
            'grid' => 0,
            'production_type' => 1,
            'recipe_name' => 'ProductivityBuffLvl3',
            'amount' => 1,
        ]);
    }

    public function test_it_returns_rejection_when_resources_are_insufficient(): void
    {
        $catalogWithCost = new ConfigProductionCatalog([
            'producers' => ['ProvisionHouse' => 1],
            'recipes' => [
                1 => [
                    [
                        'name' => 'ProductivityBuffLvl3',
                        'group' => 0,
                        'duration_seconds' => 1800,
                        'buff_type' => 'Timed',
                        'requires_upgrade_level_min' => 0,
                        'requires_upgrade_level_max' => 99,
                        'requires_event' => null,
                        'requires_quest' => null,
                        'costs' => [['resource' => 'Fish', 'count' => 120]],
                        'costs_known' => true,
                    ],
                ],
            ],
        ]);

        $policy = new ProductionOrderPolicy($catalogWithCost);
        $handler = new ProduceBuffHandler($this->zonesMock, $policy, $this->gatewayMock, $this->zoneParserMock);

        $snapshot = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 1234,
                    'buildingName_string' => 'ProvisionHouse',
                    'upgradeIsInProgress' => false,
                ],
            ],
            'production_queues' => [],
            'resources' => [
                ['name_string' => 'Fish', 'amount' => 50],
            ],
        ]);

        $this->zonesMock->shouldReceive('forAccount')
            ->once()
            ->with($this->account)
            ->andReturn($snapshot);

        $this->gatewayMock->shouldNotReceive('queueOrder');

        $payload = [
            'grid' => 1234,
            'production_type' => 1,
            'recipe_name' => 'ProductivityBuffLvl3',
            'amount' => 1,
        ];

        $result = $handler->handle($this->account, $payload);

        $this->assertSame(
            __('tasks.produce_buff.rejected.insufficient_resources', [
                'grid' => 1234,
                'recipe' => 'ProductivityBuffLvl3',
                'type' => 1,
            ]),
            $result
        );
    }
}
