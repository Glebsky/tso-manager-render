<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Zone\ZoneAmfExecutor;
use App\Services\Zone\ZoneResourceCategorizer;
use App\Services\ZoneParserService;
use Tests\TestCase;

class ZoneParserTest extends TestCase
{
    public function test_resource_categorizer_maps_names_to_warehouse_tabs(): void
    {
        $categorizer = new ZoneResourceCategorizer;

        $this->assertEquals('WarehouseTab1', $categorizer->getCategory('Wood'));
        $this->assertEquals('WarehouseTab2', $categorizer->getCategory('Bread'));
        $this->assertEquals('WarehouseTab3', $categorizer->getCategory('Coins'));
        $this->assertEquals('WarehouseTab4', $categorizer->getCategory('Granite'));
        $this->assertEquals('WarehouseTab8', $categorizer->getCategory('Platinum'));
        $this->assertEquals('WarehouseTab6', $categorizer->getCategory('EventResource'));
        $this->assertEquals('WarehouseTab6', $categorizer->getCategory('Pumpkin_2024'));
        $this->assertEquals('WarehouseTab7', $categorizer->getCategory('Collectible_Herb'));
        $this->assertEquals('Other', $categorizer->getCategory('UnknownResourceKey'));
        $this->assertEquals('Other', $categorizer->getCategory(''));
    }

    public function test_zone_parser_service_categorizes_resources(): void
    {
        $executor = $this->createMock(ZoneAmfExecutor::class);
        $executor->method('execute')->willReturn([
            'buildings' => [['id' => 1]],
            'resources' => [
                ['name' => 'Wood', 'amount' => 500],
                ['name' => 'Bread', 'amount' => 1000],
            ],
        ]);

        $categorizer = new ZoneResourceCategorizer;
        $service = new ZoneParserService($executor, $categorizer);

        $result = $service->parse('dummy_amf_data');

        $this->assertArrayHasKey('resources', $result);
        $this->assertEquals('WarehouseTab1', $result['resources'][0]['category']);
        $this->assertEquals('WarehouseTab2', $result['resources'][1]['category']);
    }

    public function test_container_resolves_zone_parser_service(): void
    {
        $service = $this->app->make(ZoneParserService::class);
        $this->assertInstanceOf(ZoneParserService::class, $service);
    }
}
