<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class FriendZoneTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $authMock;

    private MockInterface $amfMock;

    private MockInterface $zoneParserMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authMock = Mockery::mock(TsoAuthService::class);
        $this->amfMock = Mockery::mock(TsoAmfService::class);
        $this->zoneParserMock = Mockery::mock(ZoneParserService::class);

        $this->app->instance(TsoAuthService::class, $this->authMock);
        $this->app->instance(TsoAmfService::class, $this->amfMock);
        $this->app->instance(ZoneParserService::class, $this->zoneParserMock);
    }

    public function test_friend_not_in_list_returns_403(): void
    {
        $account = Account::create([
            'username' => 'testuser',
            'password' => 'secret',
            'region' => 'ru',
            'zone_data' => [
                'friends' => [
                    ['id' => 123, 'username' => 'friend_one'],
                ],
            ],
        ]);

        $response = $this->getJson("/api/accounts/{$account->id}/friends/999/zone");

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function test_successful_fetch_friend_zone_and_caching(): void
    {
        $account = Account::create([
            'username' => 'testuser',
            'password' => 'secret',
            'region' => 'ru',
            'zone_data' => [
                'friends' => [
                    ['id' => 12345, 'username' => 'Lotamount', 'nickname' => 'Lotamount'],
                ],
            ],
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')
            ->once()
            ->with(Mockery::on(fn ($acc) => $acc->id === $account->id));

        $this->amfMock->shouldReceive('getZone')
            ->once()
            ->with(Mockery::on(fn ($acc) => $acc->id === $account->id), 12345)
            ->andReturn('dummy_amf_binary');

        $this->zoneParserMock->shouldReceive('parse')
            ->once()
            ->with('dummy_amf_binary')
            ->andReturn([
                'errorCode' => 0,
                'buildings' => [
                    [
                        'buildingName_string' => 'cokingplant',
                        'buildingGrid' => 142,
                        'upgradeLevel' => 5,
                        'isProductionActive' => true,
                    ],
                ],
            ]);

        $response = $this->getJson("/api/accounts/{$account->id}/friends/12345/zone");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'friend' => [
                'id' => 12345,
                'username' => 'Lotamount',
            ],
            'buildings' => [
                [
                    'buildingName_string' => 'cokingplant',
                    'buildingGrid' => 142,
                    'upgradeLevel' => 5,
                ],
            ],
        ]);

        // Second call should be served from cache without calling amfService again
        $cachedResponse = $this->getJson("/api/accounts/{$account->id}/friends/12345/zone");
        $cachedResponse->assertStatus(200);
        $cachedResponse->assertJsonPath('buildings.0.buildingGrid', 142);
    }

    public function test_force_refresh_bypasses_cache(): void
    {
        $account = Account::create([
            'username' => 'testuser',
            'password' => 'secret',
            'region' => 'ru',
            'zone_data' => [
                'friends' => [
                    ['id' => 12345, 'username' => 'Lotamount'],
                ],
            ],
        ]);

        // Prepopulate cache with stale data
        Cache::put("friend-zone:{$account->id}:12345", json_encode([
            'errorCode' => 0,
            'buildings' => [
                ['buildingName_string' => 'old_building', 'buildingGrid' => 10],
            ],
        ]), 3600);

        // Expect network call because ?refresh=1 is passed
        $this->authMock->shouldReceive('ensureAuthenticated')
            ->once()
            ->with(Mockery::on(fn ($acc) => $acc->id === $account->id));

        $this->amfMock->shouldReceive('getZone')
            ->once()
            ->with(Mockery::on(fn ($acc) => $acc->id === $account->id), 12345)
            ->andReturn('new_raw_amf');

        $this->zoneParserMock->shouldReceive('parse')
            ->once()
            ->with('new_raw_amf')
            ->andReturn([
                'errorCode' => 0,
                'buildings' => [
                    ['buildingName_string' => 'new_building', 'buildingGrid' => 20],
                ],
            ]);

        $response = $this->getJson("/api/accounts/{$account->id}/friends/12345/zone?refresh=1");

        $response->assertStatus(200);
        $response->assertJsonPath('buildings.0.buildingName_string', 'new_building');
        $response->assertJsonPath('buildings.0.buildingGrid', 20);
    }

    public function test_1012_error_retries_and_recovers(): void
    {
        $account = Account::create([
            'username' => 'testuser',
            'password' => 'secret',
            'region' => 'ru',
            'zone_data' => [
                'friends' => [
                    ['id' => 12345, 'username' => 'Lotamount'],
                ],
            ],
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')
            ->twice()
            ->with(Mockery::on(fn ($acc) => $acc->id === $account->id));

        $this->amfMock->shouldReceive('getZone')
            ->twice()
            ->with(Mockery::on(fn ($acc) => $acc->id === $account->id), 12345)
            ->andReturn('attempt1_amf', 'attempt2_amf');

        $this->zoneParserMock->shouldReceive('parse')
            ->once()
            ->with('attempt1_amf')
            ->andReturn(['errorCode' => 1012]);

        $this->zoneParserMock->shouldReceive('parse')
            ->once()
            ->with('attempt2_amf')
            ->andReturn([
                'errorCode' => 0,
                'buildings' => [
                    ['buildingName_string' => 'ironmine', 'buildingGrid' => 55],
                ],
            ]);

        $response = $this->getJson("/api/accounts/{$account->id}/friends/12345/zone");

        $response->assertStatus(200);
        $response->assertJsonPath('buildings.0.buildingName_string', 'ironmine');
    }
}
