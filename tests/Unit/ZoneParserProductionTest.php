<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Game\Production\ConfigProductionCatalog;
use App\Services\Game\Production\ProductionQueueState;
use App\Services\ZoneParserService;
use App\Support\Zone\ZoneSnapshot;
use Tests\TestCase;

final class ZoneParserProductionTest extends TestCase
{
    private ZoneParserService $parser;

    private ConfigProductionCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = app(ZoneParserService::class);
        $this->catalog = new ConfigProductionCatalog([
            'producers' => [
                'ProvisionHouse' => 1,
                'Bookbinder' => 2,
                'ProvisionHouse2' => 5,
            ],
            'recipes' => [],
        ]);
    }

    public function test_it_parses_real_zone_snapshot_with_production_queues(): void
    {
        $fixturePath = base_path('tests/Fixtures/Amf/zone_snapshot_1002.bin');
        $this->assertFileExists($fixturePath);

        $rawAmf = (string) file_get_contents($fixturePath);
        $parsed = $this->parser->parse($rawAmf);

        $this->assertArrayHasKey('production_queues', $parsed);
        $this->assertIsArray($parsed['production_queues']);

        $snapshot = new ZoneSnapshot($parsed);
        $queues = $snapshot->productionQueues();
        $this->assertNotNull($queues);
        $this->assertCount(1, $queues);

        $queueState = $queues[0];
        $this->assertInstanceOf(ProductionQueueState::class, $queueState);
        $this->assertSame(2, $queueState->productionType);
        $this->assertSame(1, $queueState->used());
        $this->assertTrue($queueState->hasOrders());

        $this->assertCount(1, $queueState->orders);
        $order = $queueState->orders[0];
        $this->assertSame('Tome', $order['type_string']);
        $this->assertSame(1, $order['amount']);
        $this->assertSame(1, $order['produced_items']);
        $this->assertSame(259200000.0, $order['collected_time']);
        $this->assertSame(1, $order['stacks']);
        $this->assertSame(1, $order['index']);

        // Test productionQueueFor
        $queue2 = $snapshot->productionQueueFor(2);
        $this->assertNotNull($queue2);
        $this->assertSame(2, $queue2->productionType);
        $this->assertSame(1, $queue2->used());

        $queue1 = $snapshot->productionQueueFor(1);
        $this->assertNotNull($queue1);
        $this->assertSame(1, $queue1->productionType);
        $this->assertSame(0, $queue1->used());
        $this->assertFalse($queue1->hasOrders());
    }

    public function test_three_distinct_states_of_production_queues(): void
    {
        // 1. Missing / broken section -> null
        $snapshotUnavailable = new ZoneSnapshot(['buildings' => []]);
        $this->assertNull($snapshotUnavailable->productionQueues());
        $this->assertNull($snapshotUnavailable->productionQueueFor(1));

        $snapshotNull = new ZoneSnapshot(['production_queues' => null]);
        $this->assertNull($snapshotNull->productionQueues());
        $this->assertNull($snapshotNull->productionQueueFor(1));

        // 2. Present but empty -> []
        $snapshotEmpty = new ZoneSnapshot(['production_queues' => []]);
        $this->assertSame([], $snapshotEmpty->productionQueues());
        $emptyQueue = $snapshotEmpty->productionQueueFor(1);
        $this->assertNotNull($emptyQueue);
        $this->assertSame(0, $emptyQueue->used());
        $this->assertFalse($emptyQueue->hasOrders());

        // 3. Present and non-empty
        $snapshotFilled = new ZoneSnapshot([
            'production_queues' => [
                [
                    'production_type' => 1,
                    'orders' => [
                        [
                            'type_string' => 'ProductivityBuffLvl3',
                            'amount' => 3,
                            'produced_items' => 0,
                            'collected_time' => 0.0,
                            'stacks' => 1,
                            'index' => 0,
                        ],
                    ],
                ],
            ],
        ]);
        $queuesFilled = $snapshotFilled->productionQueues();
        $this->assertNotNull($queuesFilled);
        $this->assertCount(1, $queuesFilled);
        $filledQueue = $snapshotFilled->productionQueueFor(1);
        $this->assertNotNull($filledQueue);
        $this->assertSame(1, $filledQueue->used());
        $this->assertTrue($filledQueue->hasOrders());
    }

    public function test_producer_buildings_filters_and_formats_correctly(): void
    {
        $snapshot = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 1234,
                    'buildingName_string' => 'ProvisionHouse',
                    'upgradeLevel' => 4,
                    'upgradeIsInProgress' => false,
                    'isProductionActive' => true,
                ],
                [
                    'buildingGrid' => 5678,
                    'buildingName_string' => 'Woodcutter',
                    'upgradeLevel' => 3,
                    'upgradeIsInProgress' => false,
                    'isProductionActive' => true,
                ],
                [
                    'buildingGrid' => 9000,
                    'buildingName_string' => 'Bookbinder',
                    'upgradeLevel' => 2,
                    'upgradeIsInProgress' => true,
                    'isProductionActive' => false,
                ],
            ],
        ]);

        $producers = $snapshot->producerBuildings($this->catalog);
        $this->assertCount(2, $producers);

        $this->assertSame(1234, $producers[0]['grid']);
        $this->assertSame('ProvisionHouse', $producers[0]['building_name']);
        $this->assertSame(1, $producers[0]['production_type']);
        $this->assertSame(4, $producers[0]['upgrade_level']);
        $this->assertFalse($producers[0]['upgrade_in_progress']);
        $this->assertTrue($producers[0]['production_active']);

        $this->assertSame(9000, $producers[1]['grid']);
        $this->assertSame('Bookbinder', $producers[1]['building_name']);
        $this->assertSame(2, $producers[1]['production_type']);
        $this->assertSame(2, $producers[1]['upgrade_level']);
        $this->assertTrue($producers[1]['upgrade_in_progress']);
        $this->assertFalse($producers[1]['production_active']);
    }
}
