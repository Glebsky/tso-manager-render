<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BuildingNotClickableException;
use App\Exceptions\GameServerErrorException;
use App\Models\Account;
use App\Services\Tasks\Handlers\CollectBuildingHandler;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ScheduledTaskCollectBuildingTest extends TestCase
{
    use RefreshDatabase;

    /** @var mixed */
    private $authMock;

    /** @var mixed */
    private $amfMock;

    /** @var mixed */
    private $parserMock;

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

    private function handler(): CollectBuildingHandler
    {
        return $this->app->make(CollectBuildingHandler::class);
    }

    /**
     * @param  array<int, array<string, mixed>>  $buildings
     */
    private function mockZone(array $buildings, int $errorCode = 0): void
    {
        $this->amfMock->shouldReceive('getZone')->once()->andReturn('ZONE_AMF');
        $this->parserMock->shouldReceive('parse')->with('ZONE_AMF')->once()->andReturn([
            'errorCode' => $errorCode,
            'buildings' => $buildings,
        ]);
    }

    public function test_collectible_mode_sends_command_65_with_grid(): void
    {
        $account = $this->createAccount();

        $this->mockZone([
            ['buildingGrid' => 7215, 'buildingName' => 'CollectibleHerbsBuilding'],
        ]);

        $this->amfMock->shouldReceive('collectCollectible')
            ->once()
            ->with($account, 7215)
            ->andReturn('COLLECT_AMF');

        $this->parserMock->shouldReceive('parse')->with('COLLECT_AMF')->andReturn(['errorCode' => 0]);

        $result = $this->handler()->handle($account, [
            'grid' => 7215,
            'mode' => 'collectible',
        ]);

        $this->assertSame(
            __('tasks.building_collect.collected', ['name' => 'CollectibleHerbsBuilding', 'grid' => 7215]),
            $result
        );
    }

    public function test_quest_trigger_mode_sends_command_100_with_grid_in_data(): void
    {
        $account = $this->createAccount();

        $this->mockZone([
            ['buildingGrid' => 7301, 'buildingName' => 'FlyingHouse'],
        ]);

        $this->amfMock->shouldReceive('sendBuildingSelectedQuestTrigger')
            ->once()
            ->with($account, 7301)
            ->andReturn('QUEST_AMF');

        $this->amfMock->shouldNotReceive('collectCollectible');

        $this->parserMock->shouldReceive('parse')->with('QUEST_AMF')->andReturn(['errorCode' => 0]);

        $result = $this->handler()->handle($account, [
            'grid' => 7301,
            'mode' => 'quest_trigger',
        ]);

        $this->assertSame(
            __('tasks.building_collect.gift_received', ['name' => 'FlyingHouse', 'grid' => 7301]),
            $result
        );
    }

    public function test_quest_trigger_mode_never_sends_destruct_command(): void
    {
        $account = $this->createAccount();

        $this->mockZone([
            ['buildingGrid' => 7301, 'buildingName' => 'CollectibleHerbsBuilding'],
        ]);

        $this->amfMock->shouldReceive('sendBuildingSelectedQuestTrigger')
            ->once()
            ->with($account, 7301)
            ->andReturn('QUEST_AMF');

        $this->amfMock->shouldNotReceive('collectCollectible');

        $this->parserMock->shouldReceive('parse')->with('QUEST_AMF')->andReturn(['errorCode' => 0]);

        $result = $this->handler()->handle($account, [
            'grid' => 7301,
            'mode' => 'quest_trigger',
        ]);

        $this->assertSame(
            __('tasks.building_collect.gift_received', ['name' => 'CollectibleHerbsBuilding', 'grid' => 7301]),
            $result
        );
    }

    public function test_collectible_mode_on_production_building_sends_nothing(): void
    {
        $account = $this->createAccount();

        $this->mockZone([
            ['buildingGrid' => 4500, 'buildingName' => 'Woodcutter'],
        ]);

        $this->amfMock->shouldNotReceive('collectCollectible');
        $this->amfMock->shouldNotReceive('sendBuildingSelectedQuestTrigger');

        $this->expectException(BuildingNotClickableException::class);

        $this->handler()->handle($account, [
            'grid' => 4500,
            'mode' => 'collectible',
        ]);
    }

    public function test_auto_mode_falls_back_to_quest_trigger_for_unknown_building(): void
    {
        $account = $this->createAccount();

        $this->mockZone([
            ['buildingGrid' => 5500, 'buildingName' => 'ChristmasTree'],
        ]);

        $this->amfMock->shouldReceive('sendBuildingSelectedQuestTrigger')
            ->once()
            ->with($account, 5500)
            ->andReturn('QUEST_AMF');

        $this->amfMock->shouldNotReceive('collectCollectible');

        $this->parserMock->shouldReceive('parse')->with('QUEST_AMF')->andReturn(['errorCode' => 0]);

        $result = $this->handler()->handle($account, [
            'grid' => 5500,
            'mode' => 'auto',
        ]);

        $this->assertSame(
            __('tasks.building_collect.gift_received', ['name' => 'ChristmasTree', 'grid' => 5500]),
            $result
        );
    }

    public function test_missing_building_is_skipped_without_failure(): void
    {
        $account = $this->createAccount();

        $this->mockZone([
            ['buildingGrid' => 1000, 'buildingName' => 'Woodcutter'],
        ]);

        $this->amfMock->shouldNotReceive('collectCollectible');
        $this->amfMock->shouldNotReceive('sendBuildingSelectedQuestTrigger');

        $result = $this->handler()->handle($account, [
            'grid' => 9999,
            'mode' => 'auto',
        ]);

        $this->assertSame(
            __('tasks.building_collect.not_found', ['grid' => 9999]),
            $result
        );
    }

    public function test_quest_trigger_error_551_is_skipped(): void
    {
        $account = $this->createAccount();

        $this->mockZone([
            ['buildingGrid' => 7301, 'buildingName' => 'FlyingHouse'],
        ]);

        $this->amfMock->shouldReceive('sendBuildingSelectedQuestTrigger')
            ->once()
            ->with($account, 7301)
            ->andReturn('QUEST_AMF');

        $this->parserMock->shouldReceive('parse')->with('QUEST_AMF')->andReturn(['errorCode' => 551]);

        $result = $this->handler()->handle($account, [
            'grid' => 7301,
            'mode' => 'quest_trigger',
        ]);

        $this->assertSame(
            __('tasks.building_collect.nothing_to_collect', ['name' => 'FlyingHouse', 'grid' => 7301]),
            $result
        );
    }

    public function test_session_error_1005_is_rethrown(): void
    {
        $account = $this->createAccount();

        $this->mockZone([
            ['buildingGrid' => 7215, 'buildingName' => 'CollectibleHerbsBuilding'],
        ]);

        $this->amfMock->shouldReceive('collectCollectible')
            ->once()
            ->with($account, 7215)
            ->andReturn('COLLECT_AMF');

        $this->parserMock->shouldReceive('parse')->with('COLLECT_AMF')->andReturn(['errorCode' => 1005]);

        $this->expectException(GameServerErrorException::class);

        $this->handler()->handle($account, [
            'grid' => 7215,
            'mode' => 'collectible',
        ]);
    }

    public function test_zone_error_raises_game_server_error(): void
    {
        $account = $this->createAccount();

        $this->mockZone([], 1012);

        $this->expectException(GameServerErrorException::class);

        $this->handler()->handle($account, [
            'grid' => 7215,
            'mode' => 'auto',
        ]);
    }

    public function test_request_rejects_missing_grid_and_unknown_mode(): void
    {
        $account = $this->createAccount();

        $res1 = $this->postJson('/api/tasks', [
            'account_id' => $account->id,
            'task_type' => 'collect_building',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->addHour()->toDateTimeString(),
            'payload' => [
                'mode' => 'auto',
            ],
        ]);
        $res1->assertStatus(422);
        $res1->assertJsonValidationErrors(['payload.grid']);

        $res2 = $this->postJson('/api/tasks', [
            'account_id' => $account->id,
            'task_type' => 'collect_building',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->addHour()->toDateTimeString(),
            'payload' => [
                'grid' => 7215,
                'mode' => 'invalid_mode',
            ],
        ]);
        $res2->assertStatus(422);
        $res2->assertJsonValidationErrors(['payload.mode']);

        $res3 = $this->postJson('/api/tasks', [
            'account_id' => $account->id,
            'task_type' => 'collect_building',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->addHour()->toDateTimeString(),
            'payload' => [
                'grid' => 7215,
                'mode' => 'auto',
            ],
        ]);
        $res3->assertStatus(201);
    }

    public function test_sequence_step_uses_the_same_handler(): void
    {
        $account = $this->createAccount();

        $response = $this->postJson('/api/tasks', [
            'account_id' => $account->id,
            'task_type' => 'sequence',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->addHour()->toDateTimeString(),
            'payload' => [
                'actions' => [
                    [
                        'task_type' => 'collect_building',
                        'delay_seconds' => 1,
                        'payload' => [
                            'grid' => 7215,
                            'mode' => 'auto',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('scheduled_tasks', [
            'account_id' => $account->id,
            'task_type' => 'sequence',
        ]);
    }
}
