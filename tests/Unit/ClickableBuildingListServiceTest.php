<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Account;
use App\Services\Game\ClickableBuildingListService;
use App\Services\Game\ClickableBuildingRegistry;
use App\Services\Game\QuestTriggerBuildingProvider;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ClickableBuildingListServiceTest extends TestCase
{
    private ClickableBuildingRegistry $registry;

    /** @var mixed */
    private $questProviderMock;

    /** @var mixed */
    private $parserMock;

    /** @var mixed */
    private $amfMock;

    private ClickableBuildingListService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ClickableBuildingRegistry([
            ['pattern' => '/^Collectible.+Building$/i', 'kind' => 0],
            ['pattern' => '/^StarfallStarDust.*$/i', 'kind' => 1],
        ]);

        $this->questProviderMock = Mockery::mock(QuestTriggerBuildingProvider::class);
        $this->parserMock = Mockery::mock(ZoneParserService::class);
        $this->amfMock = Mockery::mock(TsoAmfService::class);

        $this->app->instance(ClickableBuildingRegistry::class, $this->registry);
        $this->app->instance(QuestTriggerBuildingProvider::class, $this->questProviderMock);
        $this->app->instance(ZoneParserService::class, $this->parserMock);
        $this->app->instance(TsoAmfService::class, $this->amfMock);

        $this->service = $this->app->make(ClickableBuildingListService::class);
    }

    public function test_row_1_collectible_allowlist_match_returns_collectible_and_true(): void
    {
        $dto = $this->service->classifyBuilding(7215, 'CollectibleHerbsBuilding', ['FlyingHouse']);

        $this->assertSame(7215, $dto->grid);
        $this->assertSame('CollectibleHerbsBuilding', $dto->buildingName);
        $this->assertSame('collectible', $dto->kind);
        $this->assertTrue($dto->available);
    }

    public function test_row_2_active_quest_trigger_match_returns_quest_gift_and_true(): void
    {
        $dto = $this->service->classifyBuilding(7301, 'FlyingHouse', ['FlyingHouse', 'ChristmasTree']);

        $this->assertSame(7301, $dto->grid);
        $this->assertSame('FlyingHouse', $dto->buildingName);
        $this->assertSame('quest_gift', $dto->kind);
        $this->assertTrue($dto->available);
    }

    public function test_row_3_known_gift_not_in_active_triggers_returns_quest_gift_and_false(): void
    {
        $dto = $this->service->classifyBuilding(7301, 'FlyingHouse', ['BalloonMarket']);

        $this->assertSame(7301, $dto->grid);
        $this->assertSame('FlyingHouse', $dto->buildingName);
        $this->assertSame('quest_gift', $dto->kind);
        $this->assertFalse($dto->available);
    }

    public function test_row_4_ordinary_production_building_returns_none_and_null(): void
    {
        $dto = $this->service->classifyBuilding(1000, 'Woodcutter', ['FlyingHouse']);

        $this->assertSame(1000, $dto->grid);
        $this->assertSame('Woodcutter', $dto->buildingName);
        $this->assertSame('none', $dto->kind);
        $this->assertNull($dto->available);
    }

    public function test_row_5_gift_building_when_pool_is_null_returns_quest_gift_and_null(): void
    {
        $dto = $this->service->classifyBuilding(7301, 'FlyingHouse', null);

        $this->assertSame(7301, $dto->grid);
        $this->assertSame('FlyingHouse', $dto->buildingName);
        $this->assertSame('quest_gift', $dto->kind);
        $this->assertNull($dto->available);
    }

    public function test_for_account_caches_list_and_clear_cache_invalidates(): void
    {
        $account = new Account([
            'username' => 'testuser',
            'password' => 'secret',
            'zone_data' => [
                'buildings' => [
                    ['buildingGrid' => 7215, 'buildingName' => 'CollectibleHerbsBuilding'],
                    ['buildingGrid' => 7301, 'buildingName' => 'FlyingHouse'],
                    ['buildingGrid' => 1000, 'buildingName' => 'Woodcutter'],
                ],
            ],
        ]);
        $account->id = 42;

        $this->questProviderMock->shouldReceive('forAccount')
            ->once()
            ->with($account)
            ->andReturn(['FlyingHouse']);

        $result1 = $this->service->forAccount($account);
        $this->assertCount(3, $result1);
        $this->assertTrue(Cache::has(ClickableBuildingListService::cacheKey(42)));

        // Second call uses cache, no call to questProviderMock
        $result2 = $this->service->forAccount($account);
        $this->assertCount(3, $result2);
        $this->assertSame('collectible', $result2[0]->kind);
        $this->assertSame('quest_gift', $result2[1]->kind);
        $this->assertTrue($result2[1]->available);

        // Clear cache
        ClickableBuildingListService::clearCache(42);
        $this->assertFalse(Cache::has(ClickableBuildingListService::cacheKey(42)));
    }
}
