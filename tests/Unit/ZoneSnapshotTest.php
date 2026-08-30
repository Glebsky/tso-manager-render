<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Zone\ZoneSnapshot;
use PHPUnit\Framework\TestCase;

class ZoneSnapshotTest extends TestCase
{
    public function test_parses_null_and_empty_data(): void
    {
        $snapshot = ZoneSnapshot::fromData(null);

        $this->assertNull($snapshot->avatarId());
        $this->assertNull($snapshot->buildingCount());
        $this->assertNull($snapshot->serverName());
        $this->assertSame([], $snapshot->buildings());
        $this->assertSame([], $snapshot->specialists());
        $this->assertSame([], $snapshot->buffs());
    }

    public function test_parses_valid_array_data(): void
    {
        $data = [
            'avatarId' => 42,
            'gameWorldName' => 'Tandria',
            'buildings' => [
                ['buildingGrid' => 101, 'name' => 'Storehouse'],
                ['buildingGrid' => 102, 'name' => 'Mayor House'],
            ],
            'specialists' => [
                ['id' => 1, 'name' => 'Geologist'],
            ],
            'buffs' => [
                ['id' => 5, 'name' => 'Fish Steak'],
            ],
        ];

        $snapshot = ZoneSnapshot::fromData($data);

        $this->assertSame(42, $snapshot->avatarId());
        $this->assertSame(2, $snapshot->buildingCount());
        $this->assertSame('Tandria', $snapshot->serverName());
        $this->assertCount(2, $snapshot->buildings());
        $this->assertCount(1, $snapshot->specialists());
        $this->assertCount(1, $snapshot->buffs());
    }

    public function test_parses_json_string_data(): void
    {
        $json = json_encode([
            'avatarId' => '77',
            'gameWorldName' => 'Wildblume',
            'buildings' => [
                ['buildingGrid' => 50],
            ],
        ]);

        $snapshot = ZoneSnapshot::fromData($json);

        $this->assertSame(77, $snapshot->avatarId());
        $this->assertSame(1, $snapshot->buildingCount());
        $this->assertSame('Wildblume', $snapshot->serverName());
    }

    public function test_handles_malformed_json_gracefully(): void
    {
        $snapshot = ZoneSnapshot::fromData('{invalid json string');

        $this->assertNull($snapshot->avatarId());
        $this->assertNull($snapshot->buildingCount());
        $this->assertNull($snapshot->serverName());
        $this->assertSame([], $snapshot->buildings());
    }
}
