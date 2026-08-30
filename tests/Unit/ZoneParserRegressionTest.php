<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ZoneParserService;
use App\Support\Zone\ZoneSnapshot;
use Tests\TestCase;

final class ZoneParserRegressionTest extends TestCase
{
    private ZoneParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = app(ZoneParserService::class);
    }

    public function test_it_preserves_all_standard_zone_keys_and_structures(): void
    {
        $fixturePath = base_path('tests/Fixtures/Amf/zone_snapshot_1002.bin');
        $this->assertFileExists($fixturePath);

        $rawAmf = (string) file_get_contents($fixturePath);
        $parsed = $this->parser->parse($rawAmf);

        // Verify standard keys exist
        $this->assertArrayHasKey('buildings', $parsed);
        $this->assertArrayHasKey('specialists', $parsed);
        $this->assertArrayHasKey('buffs', $parsed);
        $this->assertArrayHasKey('resources', $parsed);
        $this->assertArrayHasKey('friends', $parsed);
        $this->assertArrayHasKey('players', $parsed);
        $this->assertArrayHasKey('level', $parsed);
        $this->assertArrayHasKey('xp', $parsed);
        $this->assertArrayHasKey('resourceLimit', $parsed);
        $this->assertArrayHasKey('pickups', $parsed);
        $this->assertArrayHasKey('deposits', $parsed);
        $this->assertArrayHasKey('build_queue', $parsed);
        $this->assertArrayHasKey('production_queues', $parsed);

        $snapshot = new ZoneSnapshot($parsed);
        $this->assertNotNull($snapshot->buildingCount());
        $this->assertGreaterThan(0, $snapshot->buildingCount());
        $this->assertNotEmpty($snapshot->buildings());
        $this->assertNotEmpty($snapshot->specialists());
        $this->assertNotEmpty($snapshot->buffs());
    }

    public function test_legacy_snapshot_without_production_queues_does_not_break(): void
    {
        $legacyData = [
            'avatarId' => 12345,
            'gameWorldName' => 'Realm1',
            'buildings' => [
                ['buildingGrid' => 100, 'buildingName' => 'Woodcutter'],
            ],
            'specialists' => [],
            'buffs' => [],
        ];

        $snapshot = new ZoneSnapshot($legacyData);
        $this->assertSame(12345, $snapshot->avatarId());
        $this->assertSame('Realm1', $snapshot->serverName());
        $this->assertSame(1, $snapshot->buildingCount());
        $this->assertNull($snapshot->productionQueues());
        $this->assertNull($snapshot->productionQueueFor(1));
    }
}
