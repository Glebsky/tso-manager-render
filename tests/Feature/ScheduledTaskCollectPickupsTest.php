<?php

namespace Tests\Feature;

use App\Exceptions\PickupsUnavailableException;
use App\Models\Account;
use App\Services\Tasks\Handlers\CollectPickupsHandler;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ScheduledTaskCollectPickupsTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $authMock;

    private MockInterface $amfMock;

    private MockInterface $parserMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authMock = Mockery::mock(TsoAuthService::class);
        $this->amfMock = Mockery::mock(TsoAmfService::class);
        $this->parserMock = Mockery::mock(ZoneParserService::class);

        $this->app->instance(TsoAuthService::class, $this->authMock);
        $this->app->instance(TsoAmfService::class, $this->amfMock);
        $this->app->instance(ZoneParserService::class, $this->parserMock);
    }

    private function createAccount(): Account
    {
        return Account::create([
            'username' => 'testuser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'testuser',
            'dso_auth_user' => '10001',
            'zone_data' => json_encode(['friends' => [], 'availableBuffs' => []]),
        ]);
    }

    private function handler(): CollectPickupsHandler
    {
        return $this->app->make(CollectPickupsHandler::class);
    }

    /**
     * @param  array<int, array<string, mixed>>  $pickups
     */
    private function mockZone(array $pickups, ?int $errorCode = 0): void
    {
        $this->amfMock->shouldReceive('getZone')->once()->andReturn('ZONE_AMF');
        $this->parserMock->shouldReceive('parse')->with('ZONE_AMF')->once()->andReturn([
            'errorCode' => $errorCode,
            'pickups' => $pickups,
        ]);
    }

    public function test_collects_every_available_pickup(): void
    {
        $account = $this->createAccount();

        $this->mockZone([
            ['unique_id1' => 1, 'unique_id2' => 11, 'type' => 0, 'resource' => 'Wood', 'grid' => 100],
            ['unique_id1' => 2, 'unique_id2' => 22, 'type' => 1, 'resource' => 'Coin', 'grid' => 200],
        ]);

        $this->amfMock->shouldReceive('collectCollectible')->once()->with($account, 100)->andReturn('OK_1');
        $this->amfMock->shouldReceive('collectCollectible')->once()->with($account, 200)->andReturn('OK_2');
        $this->parserMock->shouldReceive('parse')->with('OK_1')->andReturn(['errorCode' => 0]);
        $this->parserMock->shouldReceive('parse')->with('OK_2')->andReturn(['errorCode' => 0]);

        $result = $this->handler()->handle($account, ['delay_ms' => 0]);

        $this->assertStringContainsString('2/2', $result);
    }

    public function test_event_filter_only_collects_event_pickups(): void
    {
        $account = $this->createAccount();

        $this->mockZone([
            ['unique_id1' => 1, 'unique_id2' => 11, 'type' => 0, 'resource' => 'Wood', 'grid' => 100],
            ['unique_id1' => 2, 'unique_id2' => 22, 'type' => 1, 'resource' => 'Coin', 'grid' => 200],
        ]);

        $this->amfMock->shouldReceive('collectCollectible')->once()->with($account, 200)->andReturn('OK_2');
        $this->amfMock->shouldNotReceive('collectCollectible')->with($account, 100);
        $this->parserMock->shouldReceive('parse')->with('OK_2')->andReturn(['errorCode' => 0]);

        $result = $this->handler()->handle($account, ['pickup_type' => 'event', 'delay_ms' => 0]);

        $this->assertStringContainsString('1/1', $result);
    }

    public function test_stale_pickup_is_skipped_and_does_not_fail_the_task(): void
    {
        $account = $this->createAccount();

        $this->mockZone([
            ['uniqueID1' => 1, 'uniqueID2' => 11, 'type' => 0, 'resource' => 'Wood', 'grid' => 100],
            ['uniqueID1' => 2, 'uniqueID2' => 22, 'type' => 0, 'resource' => 'Stone', 'grid' => 200],
        ]);

        $this->amfMock->shouldReceive('collectCollectible')->once()->with($account, 100)->andReturn('OK_1');
        $this->amfMock->shouldReceive('collectCollectible')->once()->with($account, 200)->andReturn('STALE');
        $this->parserMock->shouldReceive('parse')->with('OK_1')->andReturn(['errorCode' => 0]);
        $this->parserMock->shouldReceive('parse')->with('STALE')->andReturn(['errorCode' => 2001]);

        $result = $this->handler()->handle($account, ['delay_ms' => 0]);

        $this->assertStringContainsString('1/2', $result);
        $this->assertStringContainsString('2001', $result);
    }

    public function test_missing_pickups_key_throws_pickups_unavailable(): void
    {
        $account = $this->createAccount();

        $this->amfMock->shouldReceive('getZone')->once()->andReturn('ZONE_AMF');
        $this->parserMock->shouldReceive('parse')->with('ZONE_AMF')->once()->andReturn(['errorCode' => 0]);

        $this->expectException(PickupsUnavailableException::class);

        $this->handler()->handle($account, []);
    }

    public function test_empty_island_returns_nothing_to_collect(): void
    {
        $account = $this->createAccount();

        $this->mockZone([]);
        $this->amfMock->shouldNotReceive('executePickup');

        $result = $this->handler()->handle($account, []);

        $this->assertSame(__('tasks.pickups.none_available'), $result);
    }

    public function test_can_schedule_collect_pickups_task_via_api(): void
    {
        $account = $this->createAccount();

        $response = $this->postJson('/api/tasks', [
            'account_id' => $account->id,
            'task_type' => 'collect_pickups',
            'schedule_type' => 'interval',
            'interval_hours' => 0,
            'interval_minutes' => 30,
            'payload' => [
                'pickup_type' => 'all',
                'delay_ms' => 250,
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('scheduled_tasks', [
            'account_id' => $account->id,
            'task_type' => 'collect_pickups',
        ]);
    }

    public function test_invalid_pickup_type_is_rejected(): void
    {
        $account = $this->createAccount();

        $response = $this->postJson('/api/tasks', [
            'account_id' => $account->id,
            'task_type' => 'collect_pickups',
            'schedule_type' => 'interval',
            'interval_hours' => 0,
            'interval_minutes' => 30,
            'payload' => [
                'pickup_type' => 'golden',
            ],
        ]);

        $response->assertStatus(422);
    }
}
