<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\TaskExecutionService;
use App\Services\Tasks\Handlers\ApplyBuffHandler;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Contracts\Cache\Repository;
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

    public function test_can_update_friend_buff_task_when_friend_zone_not_cached(): void
    {
        $friendId = 20002;
        $account = $this->createAccount(
            [['id' => $friendId, 'username' => 'MyFriend', 'playerLevel' => 45]],
            [['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 5, 'buffName_string' => 'AuntIrma']]
        );

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Original Friend Buff Task',
            'task_type' => 'apply_buff',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->addDay(),
            'payload' => [
                'target_scope' => 'friend',
                'target_player_id' => $friendId,
                'target_player_name' => 'MyFriend',
                'grid' => 888,
                'unique_id1' => 11,
                'unique_id2' => 22,
                'amount' => 1,
            ],
            'is_active' => true,
        ]);

        // Ensure friend zone is NOT in cache
        Cache::forget("friend-zone:{$account->id}:{$friendId}");

        $response = $this->putJson("/api/tasks/{$task->id}", [
            'name' => 'Updated Friend Buff Task Name',
            'account_id' => $account->id,
            'task_type' => 'apply_buff',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->addDays(2)->toIso8601String(),
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

        $response->assertStatus(200);
        $task->refresh();
        $this->assertSame('Updated Friend Buff Task Name', $task->name);
    }

    public function test_can_update_sequence_with_friend_buff_task_when_friend_zone_not_cached(): void
    {
        $friendId = 20002;
        $account = $this->createAccount(
            [['id' => $friendId, 'username' => 'MyFriend', 'playerLevel' => 45]],
            [['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 5, 'buffName_string' => 'AuntIrma']]
        );

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Sequence with Friend Buff',
            'task_type' => 'sequence',
            'schedule_type' => 'daily',
            'run_at_time' => '08:00',
            'payload' => [
                'actions' => [
                    [
                        'task_type' => 'apply_buff',
                        'delay_seconds' => 5,
                        'payload' => [
                            'target_scope' => 'friend',
                            'target_player_id' => $friendId,
                            'target_player_name' => 'MyFriend',
                            'grid' => 888,
                            'unique_id1' => 11,
                            'unique_id2' => 22,
                            'amount' => 1,
                        ],
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        Cache::forget("friend-zone:{$account->id}:{$friendId}");

        $response = $this->putJson("/api/tasks/{$task->id}", [
            'name' => 'Updated Sequence with Friend Buff',
            'account_id' => $account->id,
            'task_type' => 'sequence',
            'schedule_type' => 'daily',
            'run_at_time' => '09:30',
            'payload' => [
                'actions' => [
                    [
                        'task_type' => 'apply_buff',
                        'delay_seconds' => 10,
                        'payload' => [
                            'target_scope' => 'friend',
                            'target_player_id' => $friendId,
                            'target_player_name' => 'MyFriend',
                            'grid' => 888,
                            'unique_id1' => 11,
                            'unique_id2' => 22,
                            'amount' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200);
        $task->refresh();
        $this->assertSame('Updated Sequence with Friend Buff', $task->name);
        $this->assertStringStartsWith('09:30', (string) $task->run_at_time);
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

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

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

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

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

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

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

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

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

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

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

    public function test_refreshes_friend_list_from_server_when_friend_not_in_cache_and_succeeds(): void
    {
        $friendId = 30003;
        // Account has EMPTY friends list initially
        $account = $this->createAccount(
            [],
            [['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 5, 'buffName_string' => 'AuntIrma']]
        );

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => 'apply_buff',
            'payload' => [
                'target_scope' => 'friend',
                'target_player_id' => $friendId,
                'target_player_name' => 'RecoveredFriend',
                'grid' => 888,
                'unique_id1' => 11,
                'unique_id2' => 22,
                'amount' => 1,
            ],
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

        // When friend is not found in cache, ApplyBuffHandler should query getFriendList
        $this->amfMock->shouldReceive('getFriendList')
            ->once()
            ->with(Mockery::any())
            ->andReturn('raw_friends_amf');

        $this->parserMock->shouldReceive('parse')
            ->once()
            ->with('raw_friends_amf')
            ->andReturn([
                'errorCode' => 0,
                'friends' => [
                    ['id' => $friendId, 'nickname' => 'RecoveredFriend', 'playerLevel' => 50],
                ],
            ]);

        // Then it loads the friend zone
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

        // Then applies buff
        $this->amfMock->shouldReceive('applyBuff')
            ->once()
            ->with(Mockery::any(), 888, 11, 22, 1, $friendId)
            ->andReturn('buff_ok_amf_response');

        $this->parserMock->shouldReceive('parse')
            ->once()
            ->with('buff_ok_amf_response')
            ->andReturn(['errorCode' => 0]);

        Artisan::call('tso:execute-tasks');

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertNotNull($task->last_run_at);
        $this->assertStringContainsString('OK:', (string) $task->last_result);

        // Account zone_data should now have the recovered friends list
        $account->refresh();
        $this->assertSame($friendId, $account->zone_data['friends'][0]['id'] ?? null);
    }

    public function test_skips_step_when_buff_missing_and_server_refresh_fails(): void
    {
        $account = $this->createAccount([], []);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Sequence with Missing Buff',
            'task_type' => 'sequence',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
            'payload' => [
                'actions' => [
                    [
                        'task_type' => 'apply_buff',
                        'delay_seconds' => 0,
                        'payload' => [
                            'target_scope' => 'self',
                            'grid' => 1500,
                            'unique_id1' => 11,
                            'unique_id2' => 22,
                            'buff_raw_name' => 'AuntIrma',
                            'buff_name' => 'Aunt Irma',
                            'amount' => 1,
                        ],
                    ],
                    [
                        'task_type' => 'apply_buff',
                        'delay_seconds' => 0,
                        'payload' => [
                            'target_scope' => 'self',
                            'grid' => 1600,
                            'unique_id1' => 11,
                            'unique_id2' => 22,
                            'buff_raw_name' => 'AuntIrma',
                            'buff_name' => 'Aunt Irma',
                            'amount' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

        // Exactly ONE live getZone refresh for home zone should occur across all steps
        $this->amfMock->shouldReceive('getZone')
            ->once()
            ->with(Mockery::any())
            ->andReturn('raw_empty_zone_amf');

        $this->parserMock->shouldReceive('parse')
            ->once()
            ->with('raw_empty_zone_amf')
            ->andReturn(['errorCode' => 0, 'availableBuffs' => []]);

        // applyBuff should NEVER be called since buff is missing
        $this->amfMock->shouldNotReceive('applyBuff');

        Artisan::call('tso:execute-tasks');

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertNotNull($task->last_run_at);
        $this->assertStringContainsString('SKIPPED:', (string) $task->last_result);

        $stepResults = $task->payload['step_results'] ?? [];
        $this->assertCount(2, $stepResults);
        $this->assertSame('skipped', $stepResults[0]['status']);
        $this->assertSame('skipped', $stepResults[1]['status']);
    }

    public function test_switches_to_another_stack_of_same_buff_type(): void
    {
        $account = $this->createAccount([], [
            ['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 1, 'buffName_string' => 'AuntIrma'],
            ['uniqueId1' => 33, 'uniqueId2' => 44, 'amount' => 5, 'buffName_string' => 'AuntIrma'],
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Multi-stack sequence',
            'task_type' => 'sequence',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
            'payload' => [
                'actions' => [
                    [
                        'task_type' => 'apply_buff',
                        'delay_seconds' => 0,
                        'payload' => [
                            'target_scope' => 'self',
                            'grid' => 1500,
                            'unique_id1' => 11,
                            'unique_id2' => 22,
                            'buff_raw_name' => 'AuntIrma',
                            'amount' => 1,
                        ],
                    ],
                    [
                        'task_type' => 'apply_buff',
                        'delay_seconds' => 0,
                        'payload' => [
                            'target_scope' => 'self',
                            'grid' => 1600,
                            'unique_id1' => 11,
                            'unique_id2' => 22,
                            'buff_raw_name' => 'AuntIrma',
                            'amount' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

        // First action uses stack (11, 22)
        $this->amfMock->shouldReceive('applyBuff')
            ->once()
            ->with(Mockery::any(), 1500, 11, 22, 1)
            ->andReturn('buff_1_amf_response');

        // Second action switches to stack (33, 44)
        $this->amfMock->shouldReceive('applyBuff')
            ->once()
            ->with(Mockery::any(), 1600, 33, 44, 1)
            ->andReturn('buff_2_amf_response');

        $this->parserMock->shouldReceive('parse')
            ->twice()
            ->andReturn(['errorCode' => 0]);

        Artisan::call('tso:execute-tasks');

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertNotNull($task->last_run_at);
        $this->assertStringContainsString('OK:', (string) $task->last_result);

        $stepResults = $task->payload['step_results'] ?? [];
        $this->assertSame('completed', $stepResults[0]['status']);
        $this->assertSame('completed', $stepResults[1]['status']);
    }

    public function test_caches_friend_zone_across_multiple_steps(): void
    {
        $friendId = 20002;
        $account = $this->createAccount(
            [['id' => $friendId, 'username' => 'MyFriend', 'playerLevel' => 45]],
            [['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 5, 'buffName_string' => 'AuntIrma']]
        );

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Friend multi-buff',
            'task_type' => 'sequence',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
            'payload' => [
                'actions' => [
                    [
                        'task_type' => 'apply_buff',
                        'delay_seconds' => 0,
                        'payload' => [
                            'target_scope' => 'friend',
                            'target_player_id' => $friendId,
                            'grid' => 888,
                            'unique_id1' => 11,
                            'unique_id2' => 22,
                            'buff_raw_name' => 'AuntIrma',
                            'amount' => 1,
                        ],
                    ],
                    [
                        'task_type' => 'apply_buff',
                        'delay_seconds' => 0,
                        'payload' => [
                            'target_scope' => 'friend',
                            'target_player_id' => $friendId,
                            'grid' => 999,
                            'unique_id1' => 11,
                            'unique_id2' => 22,
                            'buff_raw_name' => 'AuntIrma',
                            'amount' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

        // Friend zone should be fetched ONCE, cached and reused for the second step
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
                    ['buildingGrid' => 999, 'buildingName' => 'Farm'],
                ],
            ]);

        $this->amfMock->shouldReceive('applyBuff')
            ->once()
            ->with(Mockery::any(), 888, 11, 22, 1, $friendId)
            ->andReturn('buff_friend_1_amf_response');

        $this->amfMock->shouldReceive('applyBuff')
            ->once()
            ->with(Mockery::any(), 999, 11, 22, 1, $friendId)
            ->andReturn('buff_friend_2_amf_response');

        $this->parserMock->shouldReceive('parse')
            ->twice()
            ->andReturn(['errorCode' => 0]);

        Artisan::call('tso:execute-tasks');

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertStringContainsString('OK:', (string) $task->last_result);
    }

    public function test_does_not_fetch_friend_zone_if_buff_is_missing(): void
    {
        $friendId = 20002;
        $account = $this->createAccount(
            [['id' => $friendId, 'username' => 'MyFriend', 'playerLevel' => 45]],
            [] // No buffs in account
        );

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Friend buff without buffs',
            'task_type' => 'sequence',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
            'payload' => [
                'actions' => [
                    [
                        'task_type' => 'apply_buff',
                        'delay_seconds' => 0,
                        'payload' => [
                            'target_scope' => 'friend',
                            'target_player_id' => $friendId,
                            'grid' => 888,
                            'unique_id1' => 11,
                            'unique_id2' => 22,
                            'buff_raw_name' => 'AuntIrma',
                            'amount' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

        // 1 server refresh of own home zone
        $this->amfMock->shouldReceive('getZone')
            ->once()
            ->with(Mockery::any())
            ->andReturn('raw_empty_home_zone');

        $this->parserMock->shouldReceive('parse')
            ->once()
            ->with('raw_empty_home_zone')
            ->andReturn(['errorCode' => 0, 'availableBuffs' => []]);

        // Friend zone must NEVER be queried
        $this->amfMock->shouldNotReceive('getZone')->with(Mockery::any(), $friendId);
        $this->amfMock->shouldNotReceive('applyBuff');

        Artisan::call('tso:execute-tasks');

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertStringContainsString('SKIPPED:', (string) $task->last_result);
        $payload = is_array($task->payload) ? $task->payload : [];
        $this->assertSame('skipped', $payload['step_results'][0]['status'] ?? null);
    }

    public function test_single_buff_task_skips_when_buff_missing(): void
    {
        $account = $this->createAccount([], []);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Single Missing Buff',
            'task_type' => 'apply_buff',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
            'payload' => [
                'target_scope' => 'self',
                'grid' => 1500,
                'unique_id1' => 11,
                'unique_id2' => 22,
                'buff_raw_name' => 'AuntIrma',
                'amount' => 1,
            ],
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

        $this->amfMock->shouldReceive('getZone')
            ->once()
            ->with(Mockery::any())
            ->andReturn('raw_empty_home_zone');

        $this->parserMock->shouldReceive('parse')
            ->once()
            ->with('raw_empty_home_zone')
            ->andReturn(['errorCode' => 0, 'availableBuffs' => []]);

        $this->amfMock->shouldNotReceive('applyBuff');

        Artisan::call('tso:execute-tasks');

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertStringContainsString('SKIPPED:', (string) $task->last_result);
        $singlePayload = is_array($task->payload) ? $task->payload : [];
        $this->assertSame('skipped', $singlePayload['step_results'][0]['status'] ?? null);
    }

    public function test_skips_step_when_building_already_buffed_and_refunds_buff(): void
    {
        $friendId = 20002;
        // Only 1 buff available in inventory!
        $account = $this->createAccount(
            [['id' => $friendId, 'username' => 'MyFriend', 'playerLevel' => 45]],
            [['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 1, 'buffName_string' => 'AuntIrma']]
        );

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Friend Buff Ineligible Skip',
            'task_type' => 'sequence',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
            'payload' => [
                'actions' => [
                    [
                        'task_type' => 'apply_buff',
                        'delay_seconds' => 0,
                        'payload' => [
                            'target_scope' => 'friend',
                            'target_player_id' => $friendId,
                            'grid' => 888, // Already buffed building
                            'unique_id1' => 11,
                            'unique_id2' => 22,
                            'buff_raw_name' => 'AuntIrma',
                            'amount' => 1,
                        ],
                    ],
                    [
                        'task_type' => 'apply_buff',
                        'delay_seconds' => 0,
                        'payload' => [
                            'target_scope' => 'friend',
                            'target_player_id' => $friendId,
                            'grid' => 999, // Unbuffed building
                            'unique_id1' => 11,
                            'unique_id2' => 22,
                            'buff_raw_name' => 'AuntIrma',
                            'amount' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

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
                    [
                        'buildingGrid' => 888,
                        'buildingName_string' => 'Woodcutter',
                        'buffs' => [['buffName_string' => 'ProductivityBuffLvl3']],
                    ],
                    [
                        'buildingGrid' => 999,
                        'buildingName_string' => 'Farm',
                        'buffs' => [],
                    ],
                ],
            ]);

        // applyBuff should only be called ONCE (for grid 999, NOT for 888)
        $this->amfMock->shouldReceive('applyBuff')
            ->once()
            ->with(Mockery::any(), 999, 11, 22, 1, $friendId)
            ->andReturn('buff_success_amf_response');

        $this->parserMock->shouldReceive('parse')
            ->once()
            ->with('buff_success_amf_response')
            ->andReturn(['errorCode' => 0]);

        Artisan::call('tso:execute-tasks');

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertStringContainsString('PARTIAL:', (string) $task->last_result);

        $stepResults = $task->payload['step_results'] ?? [];
        $this->assertSame('skipped', $stepResults[0]['status']);
        $this->assertStringContainsString('already buffed', (string) $stepResults[0]['reason']);
        $this->assertSame('completed', $stepResults[1]['status']);
    }

    public function test_skips_step_when_building_production_stopped(): void
    {
        $friendId = 20002;
        $account = $this->createAccount(
            [['id' => $friendId, 'username' => 'MyFriend', 'playerLevel' => 45]],
            [['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 5, 'buffName_string' => 'AuntIrma']]
        );

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Stopped Building Buff Skip',
            'task_type' => 'sequence',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
            'payload' => [
                'actions' => [
                    [
                        'task_type' => 'apply_buff',
                        'delay_seconds' => 0,
                        'payload' => [
                            'target_scope' => 'friend',
                            'target_player_id' => $friendId,
                            'grid' => 888,
                            'unique_id1' => 11,
                            'unique_id2' => 22,
                            'buff_raw_name' => 'AuntIrma',
                            'amount' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

        $this->amfMock->shouldReceive('getZone')
            ->once()
            ->with(Mockery::any(), $friendId)
            ->andReturn('friend_zone_stopped');

        $this->parserMock->shouldReceive('parse')
            ->once()
            ->with('friend_zone_stopped')
            ->andReturn([
                'errorCode' => 0,
                'buildings' => [
                    [
                        'buildingGrid' => 888,
                        'buildingName_string' => 'Brewery',
                        'buildingMode' => 28, // Stopped
                        'buffs' => [],
                    ],
                ],
            ]);

        $this->amfMock->shouldNotReceive('applyBuff');

        Artisan::call('tso:execute-tasks');

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertStringContainsString('SKIPPED:', (string) $task->last_result);

        $stepResults = $task->payload['step_results'] ?? [];
        $this->assertSame('skipped', $stepResults[0]['status']);
        $this->assertStringContainsString('stopped', (string) $stepResults[0]['reason']);
    }

    public function test_recovers_from_server_error_550_on_friend_island(): void
    {
        $friendId = 20002;
        $account = $this->createAccount(
            [['id' => $friendId, 'username' => 'MyFriend', 'playerLevel' => 45]],
            [['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 5, 'buffName_string' => 'AuntIrma']]
        );

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Friend Buff 550 Recovery',
            'task_type' => 'apply_buff',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
            'payload' => [
                'target_scope' => 'friend',
                'target_player_id' => $friendId,
                'grid' => 888,
                'unique_id1' => 11,
                'unique_id2' => 22,
                'buff_raw_name' => 'AuntIrma',
                'amount' => 1,
            ],
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

        // 1st getZone on attempt 1, 2nd getZone on attempt 2 after 550 cache invalidation
        $this->amfMock->shouldReceive('getZone')
            ->twice()
            ->with(Mockery::any(), $friendId)
            ->andReturn('friend_zone_amf');

        $this->parserMock->shouldReceive('parse')
            ->twice()
            ->with('friend_zone_amf')
            ->andReturn([
                'errorCode' => 0,
                'buildings' => [
                    [
                        'buildingGrid' => 888,
                        'buildingName_string' => 'IronSmelter',
                        'buffs' => [],
                    ],
                ],
            ]);

        // Attempt 1 fails with 550; Attempt 2 succeeds with 0
        $this->amfMock->shouldReceive('applyBuff')
            ->twice()
            ->with(Mockery::any(), 888, 11, 22, 1, $friendId)
            ->andReturnValues(['buff_fail_550_amf_response', 'buff_ok_amf_response']);

        $this->parserMock->shouldReceive('parse')
            ->once()
            ->with('buff_fail_550_amf_response')
            ->andReturn(['errorCode' => 550]);

        $this->parserMock->shouldReceive('parse')
            ->once()
            ->with('buff_ok_amf_response')
            ->andReturn(['errorCode' => 0]);

        Artisan::call('tso:execute-tasks');

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertStringContainsString('OK:', (string) $task->last_result);
    }

    public function test_proactively_refreshes_friend_zone_after_threshold_steps(): void
    {
        $friendId = 20002;
        $buffStacks = [];
        for ($i = 1; $i <= 16; $i++) {
            $buffStacks[] = ['uniqueId1' => $i, 'uniqueId2' => $i * 10, 'amount' => 1, 'buffName_string' => 'AuntIrma'];
        }

        $account = $this->createAccount(
            [['id' => $friendId, 'username' => 'MyFriend', 'playerLevel' => 45]],
            $buffStacks
        );

        $actions = [];
        $buildings = [];
        for ($i = 1; $i <= 16; $i++) {
            $grid = 1000 + $i;
            $actions[] = [
                'task_type' => 'apply_buff',
                'delay_seconds' => 0,
                'payload' => [
                    'target_scope' => 'friend',
                    'target_player_id' => $friendId,
                    'grid' => $grid,
                    'unique_id1' => $i,
                    'unique_id2' => $i * 10,
                    'buff_raw_name' => 'AuntIrma',
                    'amount' => 1,
                ],
            ];
            $buildings[] = [
                'buildingGrid' => $grid,
                'buildingName_string' => "Farm{$i}",
                'buffs' => [],
            ];
        }

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'name' => '16 Step Friend Buff Keepalive Test',
            'task_type' => 'sequence',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
            'payload' => [
                'actions' => $actions,
            ],
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

        // Expect getZone to be called TWICE: once at step 1, once at step 16 (after 15 actions threshold)
        $this->amfMock->shouldReceive('getZone')
            ->twice()
            ->with(Mockery::any(), $friendId)
            ->andReturn('friend_zone_keepalive_amf');

        $this->parserMock->shouldReceive('parse')
            ->twice()
            ->with('friend_zone_keepalive_amf')
            ->andReturn([
                'errorCode' => 0,
                'buildings' => $buildings,
            ]);

        $this->amfMock->shouldReceive('applyBuff')
            ->times(16)
            ->andReturn('buff_step_amf_response');

        $this->parserMock->shouldReceive('parse')
            ->times(16)
            ->with('buff_step_amf_response')
            ->andReturn(['errorCode' => 0]);

        Artisan::call('tso:execute-tasks');

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertStringContainsString('OK:', (string) $task->last_result);
    }

    public function test_self_buff_sequence_deduplicates_in_cache_without_polluting_db(): void
    {
        $account = Account::create([
            'username' => 'selfuser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'selfuser',
            'dso_auth_user' => '10001',
            'last_sync_at' => now(),
            'zone_data' => [
                'friends' => [],
                'availableBuffs' => [
                    ['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 10, 'buffName_string' => 'ProductivityBuffLvl3'],
                ],
                'buildings' => [
                    [
                        'buildingGrid' => 1500,
                        'buildingName_string' => 'Farm',
                        'buffs' => [],
                    ],
                    [
                        'buildingGrid' => 1600,
                        'buildingName_string' => 'Brewery',
                        'buffs' => [],
                    ],
                ],
            ],
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Self Sequence with Duplicate Step',
            'task_type' => 'sequence',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
            'payload' => [
                'actions' => [
                    [
                        'task_type' => 'apply_buff',
                        'delay_seconds' => 0,
                        'payload' => [
                            'target_scope' => 'self',
                            'grid' => 1500,
                            'unique_id1' => 11,
                            'unique_id2' => 22,
                            'buff_raw_name' => 'ProductivityBuffLvl3',
                            'amount' => 1,
                        ],
                    ],
                    [
                        'task_type' => 'apply_buff',
                        'delay_seconds' => 0,
                        'payload' => [
                            'target_scope' => 'self',
                            'grid' => 1500, // duplicate!
                            'unique_id1' => 11,
                            'unique_id2' => 22,
                            'buff_raw_name' => 'ProductivityBuffLvl3',
                            'amount' => 1,
                        ],
                    ],
                    [
                        'task_type' => 'apply_buff',
                        'delay_seconds' => 0,
                        'payload' => [
                            'target_scope' => 'self',
                            'grid' => 1600,
                            'unique_id1' => 11,
                            'unique_id2' => 22,
                            'buff_raw_name' => 'ProductivityBuffLvl3',
                            'amount' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

        // applyBuff should only be called TWICE (for grid 1500 and grid 1600), NOT for duplicate step 2
        $this->amfMock->shouldReceive('applyBuff')
            ->once()
            ->with(Mockery::any(), 1500, 11, 22, 1)
            ->andReturn('buff_response_1_amf_response');

        $this->amfMock->shouldReceive('applyBuff')
            ->once()
            ->with(Mockery::any(), 1600, 11, 22, 1)
            ->andReturn('buff_response_2_amf_response');

        $this->parserMock->shouldReceive('parse')
            ->twice()
            ->andReturn(['errorCode' => 0]);

        Artisan::call('tso:execute-tasks');

        $task->refresh();
        $this->assertFalse($task->is_active);

        $stepResults = $task->payload['step_results'] ?? [];
        $this->assertCount(3, $stepResults);
        $this->assertSame('completed', $stepResults[0]['status']);
        $this->assertSame('skipped', $stepResults[1]['status']);
        $this->assertStringContainsString('already buffed', (string) ($stepResults[1]['reason'] ?? ''));
        $this->assertSame('completed', $stepResults[2]['status']);

        // CRITICAL: Ensure account->zone_data in DB has NOT been polluted with fake buffs!
        $account->refresh();
        $buildingsInDb = $account->zone_data['buildings'] ?? [];
        foreach ($buildingsInDb as $b) {
            $this->assertEmpty($b['buffs'], "Building {$b['buildingGrid']} in DB should not have buffs saved to database.");
        }
    }

    public function test_strips_legacy_fake_buffs_from_db_and_allows_buffing(): void
    {
        // Account has a building poisoned with legacy fake buff ['buffName_string' => 'ProductivityBuffLvl3', 'remaining' => 1]
        $account = Account::create([
            'username' => 'legacyuser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'legacyuser',
            'dso_auth_user' => '10001',
            'last_sync_at' => now(),
            'zone_data' => [
                'friends' => [],
                'availableBuffs' => [
                    ['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 5, 'buffName_string' => 'ProductivityBuffLvl3'],
                ],
                'buildings' => [
                    [
                        'buildingGrid' => 1500,
                        'buildingName_string' => 'Farm',
                        'buffs' => [
                            [
                                'buffName_string' => 'ProductivityBuffLvl3',
                                'remaining' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Buff Cleans Fake Buff in DB',
            'task_type' => 'apply_buff',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
            'payload' => [
                'target_scope' => 'self',
                'grid' => 1500,
                'unique_id1' => 11,
                'unique_id2' => 22,
                'buff_raw_name' => 'ProductivityBuffLvl3',
                'amount' => 1,
            ],
        ]);

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

        $this->amfMock->shouldReceive('applyBuff')
            ->once()
            ->with(Mockery::any(), 1500, 11, 22, 1)
            ->andReturn('buff_ok_amf_response');

        $this->parserMock->shouldReceive('parse')
            ->once()
            ->with('buff_ok_amf_response')
            ->andReturn(['errorCode' => 0]);

        Artisan::call('tso:execute-tasks');

        $task->refresh();
        $this->assertFalse($task->is_active);
        $this->assertStringContainsString('OK:', (string) $task->last_result);

        // DB zone_data should have been sanitized and fake buffs removed
        $account->refresh();
        $zoneData = is_array($account->zone_data) ? $account->zone_data : [];
        $this->assertEmpty($zoneData['buildings'][0]['buffs'] ?? null);
    }

    public function test_invalidate_home_zone_for_account(): void
    {
        $cache = app(Repository::class);
        $accountId = 999;

        $cache->put("home-zone:version:{$accountId}", 1, 86400);
        $cache->put("home-zone:exec:{$accountId}:v1", ['some' => 'data'], 180);

        ApplyBuffHandler::invalidateHomeZoneForAccount($cache, $accountId);

        $newVersion = (int) $cache->get("home-zone:version:{$accountId}");
        $this->assertSame(2, $newVersion);
        $this->assertNull($cache->get("home-zone:exec:{$accountId}:v{$newVersion}"));
    }

    public function test_execute_single_sequence_action_invalidates_friend_zone_cache(): void
    {
        $friendId = 40004;
        $account = $this->createAccount(
            [['id' => $friendId, 'username' => 'CacheFriend', 'playerLevel' => 50]],
            [['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 5, 'buffName_string' => 'AuntIrma']]
        );

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Sequence for Manual Single Action',
            'task_type' => 'sequence',
            'schedule_type' => 'daily',
            'run_at_time' => '12:00',
            'payload' => [
                'actions' => [
                    [
                        'task_type' => 'apply_buff',
                        'delay_seconds' => 0,
                        'payload' => [
                            'target_scope' => 'friend',
                            'target_player_id' => $friendId,
                            'target_player_name' => 'CacheFriend',
                            'grid' => 888,
                            'unique_id1' => 11,
                            'unique_id2' => 22,
                            'amount' => 1,
                        ],
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        $cache = app(Repository::class);
        $cache->put("friend-zone:version:{$account->id}", 1, 86400);

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();

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
                    ['buildingGrid' => 888, 'buildingName' => 'Farm'],
                ],
            ]);

        $this->amfMock->shouldReceive('applyBuff')
            ->once()
            ->with(Mockery::any(), 888, 11, 22, 1, $friendId)
            ->andReturn('buff_ok_amf_response');

        $this->parserMock->shouldReceive('parse')
            ->once()
            ->with('buff_ok_amf_response')
            ->andReturn(['errorCode' => 0]);

        $service = app(TaskExecutionService::class);
        $result = $service->executeSingleSequenceAction($task, 0);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(2, (int) $cache->get("friend-zone:version:{$account->id}"));
    }
}
