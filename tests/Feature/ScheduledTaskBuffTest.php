<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ScheduledTaskBuffTest extends TestCase
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

    /**
     * Helper to create account with mock zone data.
     *
     * @param  list<array<string, mixed>>  $friends
     * @param  list<array<string, mixed>>  $buffs
     */
    private function createAccount(array $friends = [], array $buffs = []): Account
    {
        return Account::create([
            'username' => 'testuser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'testuser',
            'dso_auth_user' => '10001',
            'zone_data' => json_encode([
                'friends' => $friends,
                'availableBuffs' => $buffs,
            ]),
        ]);
    }

    public function test_can_schedule_self_buff_task_with_valid_inventory(): void
    {
        $account = $this->createAccount(
            [],
            [['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 5, 'buffName_string' => 'AuntIrma']]
        );

        $response = $this->postJson('/api/tasks', [
            'account_id' => $account->id,
            'task_type' => 'apply_buff',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->addDay()->toIso8601String(),
            'payload' => [
                'target_scope' => 'self',
                'grid' => 1500,
                'unique_id1' => 11,
                'unique_id2' => 22,
                'amount' => 2,
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('scheduled_tasks', [
            'account_id' => $account->id,
            'task_type' => 'apply_buff',
        ]);
    }

    public function test_schedule_fails_when_buff_missing_or_insufficient(): void
    {
        $account = $this->createAccount(
            [],
            [['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 1, 'buffName_string' => 'AuntIrma']]
        );

        // Not in inventory
        $response1 = $this->postJson('/api/tasks', [
            'account_id' => $account->id,
            'task_type' => 'apply_buff',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->addDay()->toIso8601String(),
            'payload' => [
                'target_scope' => 'self',
                'grid' => 1500,
                'unique_id1' => 99,
                'unique_id2' => 99,
                'amount' => 1,
            ],
        ]);
        $response1->assertStatus(422);

        // Insufficient quantity
        $response2 = $this->postJson('/api/tasks', [
            'account_id' => $account->id,
            'task_type' => 'apply_buff',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->addDay()->toIso8601String(),
            'payload' => [
                'target_scope' => 'self',
                'grid' => 1500,
                'unique_id1' => 11,
                'unique_id2' => 22,
                'amount' => 5,
            ],
        ]);
        $response2->assertStatus(422);
    }

    public function test_can_schedule_friend_buff_task_when_cached(): void
    {
        $friendId = 20002;
        $account = $this->createAccount(
            [['id' => $friendId, 'username' => 'MyFriend', 'playerLevel' => 45]],
            [['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 5, 'buffName_string' => 'AuntIrma']]
        );

        $cacheKey = "friend-zone:{$account->id}:{$friendId}";
        Cache::put($cacheKey, json_encode([
            'buildings' => [
                ['buildingGrid' => 888, 'buildingName' => 'Woodcutter'],
            ],
        ]), 300);

        $response = $this->postJson('/api/tasks', [
            'account_id' => $account->id,
            'task_type' => 'apply_buff',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->addDay()->toIso8601String(),
            'payload' => [
                'target_scope' => 'friend',
                'target_player_id' => $friendId,
                'target_player_name' => 'MyFriend',
                'grid' => 888,
                'unique_id1' => 11,
                'unique_id2' => 22,
                'amount' => 1,
            ],
        ]);

        $response->assertStatus(201);
    }

    public function test_schedule_fails_when_friend_or_friend_building_missing_in_cache(): void
    {
        $friendId = 20002;
        $account = $this->createAccount(
            [['id' => $friendId, 'username' => 'MyFriend', 'playerLevel' => 45]],
            [['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 5, 'buffName_string' => 'AuntIrma']]
        );

        // Scenario A: Zone not in cache at all
        $response1 = $this->postJson('/api/tasks', [
            'account_id' => $account->id,
            'task_type' => 'apply_buff',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->addDay()->toIso8601String(),
            'payload' => [
                'target_scope' => 'friend',
                'target_player_id' => $friendId,
                'grid' => 888,
                'unique_id1' => 11,
                'unique_id2' => 22,
                'amount' => 1,
            ],
        ]);
        $response1->assertStatus(422);

        // Scenario B: Zone cached, but building grid doesn't exist
        $cacheKey = "friend-zone:{$account->id}:{$friendId}";
        Cache::put($cacheKey, json_encode([
            'buildings' => [
                ['buildingGrid' => 777, 'buildingName' => 'Woodcutter'],
            ],
        ]), 300);

        $response2 = $this->postJson('/api/tasks', [
            'account_id' => $account->id,
            'task_type' => 'apply_buff',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->addDay()->toIso8601String(),
            'payload' => [
                'target_scope' => 'friend',
                'target_player_id' => $friendId,
                'grid' => 888, // grid 888 missing in cached buildings (only 777 exists)
                'unique_id1' => 11,
                'unique_id2' => 22,
                'amount' => 1,
            ],
        ]);
        $response2->assertStatus(422);
    }

    public function test_executes_self_buff_task_successfully(): void
    {
        $account = $this->createAccount(
            [],
            [['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 5, 'buffName_string' => 'AuntIrma']]
        );

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => 'apply_buff',
            'payload' => [
                'target_scope' => 'self',
                'grid' => 1500,
                'unique_id1' => 11,
                'unique_id2' => 22,
                'amount' => 2,
            ],
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
        ]);

        $this->authMock->shouldReceive('isAuthenticated')->with(Mockery::any())->andReturn(true);

        $this->amfMock->shouldReceive('applyBuff')
            ->once()
            ->with(Mockery::any(), 1500, 11, 22, 2)
            ->andReturn('dummy_amf_response');

        $this->parserMock->shouldReceive('parse')
            ->once()
            ->with('dummy_amf_response')
            ->andReturn(['errorCode' => 0]);

        Artisan::call('tso:execute-tasks');

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertNotNull($task->last_run_at);
        $this->assertStringContainsString('OK:', (string) $task->last_result);
    }

    public function test_executes_friend_buff_task_successfully(): void
    {
        $friendId = 20002;
        $account = $this->createAccount(
            [['id' => $friendId, 'username' => 'MyFriend', 'playerLevel' => 45]],
            [['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 5, 'buffName_string' => 'AuntIrma']]
        );

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => 'apply_buff',
            'payload' => [
                'target_scope' => 'friend',
                'target_player_id' => $friendId,
                'target_player_name' => 'MyFriend',
                'grid' => 888,
                'unique_id1' => 11,
                'unique_id2' => 22,
                'amount' => 1,
            ],
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
        ]);

        $this->authMock->shouldReceive('isAuthenticated')->with(Mockery::any())->andReturn(true);

        // Should load zone fresh from game server
        $this->amfMock->shouldReceive('getZone')
            ->once()
            ->with(Mockery::any(), $friendId)
            ->andReturn('raw_friend_zone_amf');

        $this->parserMock->shouldReceive('parse')
            ->once()
            ->with('raw_friend_zone_amf')
            ->andReturn([
                'errorCode' => 0,
                'buildings' => [
                    ['buildingGrid' => 888, 'buildingName' => 'Woodcutter'],
                ],
            ]);

        // Executing applyBuff targeting friend zone ID
        $this->amfMock->shouldReceive('applyBuff')
            ->once()
            ->with(Mockery::any(), 888, 11, 22, 1, $friendId)
            ->andReturn('buff_friend_amf_response');

        $this->parserMock->shouldReceive('parse')
            ->once()
            ->with('buff_friend_amf_response')
            ->andReturn(['errorCode' => 0]);

        Artisan::call('tso:execute-tasks');

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertNotNull($task->last_run_at);
        $this->assertStringContainsString('OK:', (string) $task->last_result);
    }

    public function test_execution_fails_with_mapped_error_message(): void
    {
        $account = $this->createAccount(
            [],
            [['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 5, 'buffName_string' => 'AuntIrma']]
        );

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => 'apply_buff',
            'payload' => [
                'target_scope' => 'self',
                'grid' => 1500,
                'unique_id1' => 11,
                'unique_id2' => 22,
                'amount' => 1,
            ],
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
        ]);

        $this->authMock->shouldReceive('isAuthenticated')->with(Mockery::any())->andReturn(true);

        $this->amfMock->shouldReceive('applyBuff')
            ->once()
            ->andReturn('error_amf_response');

        $this->parserMock->shouldReceive('parse')
            ->once()
            ->with('error_amf_response')
            ->andReturn(['errorCode' => 25]); // Limit reached

        Artisan::call('tso:execute-tasks');

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertStringContainsString('tasks.error.server_error', (string) $task->last_result);
        $this->assertStringContainsString('25', (string) $task->last_result);
    }

    public function test_friend_zone_endpoint_caching(): void
    {
        $friendId = 20002;
        $account = $this->createAccount(
            [['id' => $friendId, 'username' => 'MyFriend', 'playerLevel' => 45]],
            []
        );

        $this->authMock->shouldReceive('isAuthenticated')->with(Mockery::any())->andReturn(true);

        // 1st request should hit TsoAmfService and ZoneParserService
        $this->amfMock->shouldReceive('getZone')
            ->once()
            ->with(Mockery::any(), $friendId)
            ->andReturn('friend_zone_amf');

        $this->parserMock->shouldReceive('parse')
            ->once()
            ->with('friend_zone_amf')
            ->andReturn([
                'errorCode' => 0,
                'buildings' => [
                    ['buildingGrid' => 888, 'buildingName_string' => 'Bakery'],
                ],
            ]);

        $response1 = $this->getJson("/api/accounts/{$account->id}/friends/{$friendId}/zone");

        $response1->assertStatus(200);
        $response1->assertJsonPath('success', true);
        $response1->assertJsonPath('friend.username', 'MyFriend');
        $response1->assertJsonCount(1, 'buildings');

        // 2nd request should use Cache and NOT hit services again
        $response2 = $this->getJson("/api/accounts/{$account->id}/friends/{$friendId}/zone");
        $response2->assertStatus(200);
        $response2->assertJsonCount(1, 'buildings');
    }

    public function test_retries_friend_buff_task_on_error_1012(): void
    {
        $friendId = 20002;
        $account = $this->createAccount(
            [['id' => $friendId, 'username' => 'MyFriend', 'playerLevel' => 45]],
            [['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 5, 'buffName_string' => 'AuntIrma']]
        );

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => 'apply_buff',
            'payload' => [
                'target_scope' => 'friend',
                'target_player_id' => $friendId,
                'target_player_name' => 'MyFriend',
                'grid' => 888,
                'unique_id1' => 11,
                'unique_id2' => 22,
                'amount' => 1,
            ],
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
        ]);

        $this->authMock->shouldReceive('isAuthenticated')->with(Mockery::any())->andReturn(true);

        $this->amfMock->shouldReceive('getZone')
            ->twice()
            ->with(Mockery::any(), $friendId)
            ->andReturn('raw_friend_zone_amf');

        $this->parserMock->shouldReceive('parse')
            ->twice()
            ->with('raw_friend_zone_amf')
            ->andReturnValues([
                ['errorCode' => 1012], // 1st attempt hits 1012
                [
                    'errorCode' => 0,
                    'buildings' => [
                        ['buildingGrid' => 888, 'buildingName' => 'Woodcutter'],
                    ],
                ],
            ]);

        $this->amfMock->shouldReceive('resetClient')->once();
        $this->amfMock->shouldReceive('ensureZoneLoaded')->once()->andReturn('');

        $this->amfMock->shouldReceive('applyBuff')
            ->once()
            ->with(Mockery::any(), 888, 11, 22, 1, $friendId)
            ->andReturn('buff_friend_amf_response');

        $this->parserMock->shouldReceive('parse')
            ->once()
            ->with('buff_friend_amf_response')
            ->andReturn(['errorCode' => 0]);

        Artisan::call('tso:execute-tasks');

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertNotNull($task->last_run_at);

        $this->assertStringContainsString('OK:', (string) $task->last_result);
    }
}
