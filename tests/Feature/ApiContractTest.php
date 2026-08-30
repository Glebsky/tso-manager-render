<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ScheduledTask;
use App\Models\User;
use App\Services\Game\Mines\BuildingSnapshot;
use App\Services\Game\Mines\BuildQueueSnapshot;
use App\Services\Game\Mines\Contracts\ZoneSnapshotProviderInterface;
use App\Services\Game\Mines\DepositSnapshot;
use App\Services\Game\Mines\ZoneSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($this->user);
    }

    public function test_accounts_endpoint_contract(): void
    {
        $account = Account::create([
            'username' => 'test_contract_user',
            'nickname' => 'ContractPlayer',
            'password' => 'secret123',
            'server' => 'ru_1',
            'region' => 'ru',
        ]);

        // GET /api/accounts -> 200 OK with meta.server_time
        $response = $this->getJson('/api/accounts');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'username', 'nickname', 'server_name', 'status'],
                ],
                'meta' => ['server_time'],
            ]);

        // POST /api/accounts -> 201 Created
        $storeResponse = $this->postJson('/api/accounts', [
            'username' => 'new_account_user',
            'nickname' => 'NewPlayer',
            'password' => 'secret123',
            'server' => 'ru_1',
            'region' => 'ru',
        ]);
        $storeResponse->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'username', 'nickname'],
                'meta' => ['server_time'],
            ]);

        // GET /api/game/clickable-buildings -> 200 OK
        $clickableResponse = $this->getJson('/api/game/clickable-buildings?account_id='.$account->id);
        $clickableResponse->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['grid', 'building_name', 'kind', 'available'],
                ],
            ]);

        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = \Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->shouldReceive('forAccount')
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [
                    6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000),
                ],
                buildingsByGrid: [
                    6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 1, isProductionActive: true, upgradeInProgress: false),
                ],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));
        $this->app->instance(ZoneSnapshotProviderInterface::class, $mockZones);

        // GET /api/game/buildable-deposits -> 200 OK
        $buildableResponse = $this->getJson('/api/game/buildable-deposits?account_id='.$account->id);
        $buildableResponse->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['grid', 'deposit_name', 'mine_name', 'amount', 'max_amount', 'allowed', 'reason'],
                ],
            ]);

        // GET /api/game/upgradable-mines -> 200 OK
        $upgradableResponse = $this->getJson('/api/game/upgradable-mines?account_id='.$account->id);
        $upgradableResponse->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['grid', 'building_name', 'deposit_name', 'level', 'max_level', 'is_active', 'upgrade_in_progress', 'allowed', 'reason'],
                ],
            ]);

        // DELETE /api/accounts/{id} -> 204 No Content
        $deleteResponse = $this->deleteJson('/api/accounts/'.$account->id);
        $deleteResponse->assertNoContent();
    }

    public function test_tasks_endpoint_contract_and_pagination(): void
    {
        Queue::fake();

        $account = Account::create([
            'username' => 'task_user',
            'nickname' => 'TaskPlayer',
            'password' => 'secret123',
            'server' => 'ru_1',
            'region' => 'ru',
        ]);

        $task = ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => 'collect_pickups',
            'schedule_type' => 'daily',
            'run_at_time' => '12:00',
            'payload' => ['pickup_type' => 'all'],
            'is_active' => true,
        ]);

        // GET /api/tasks -> 200 OK with paginated structure & meta.server_time
        $response = $this->getJson('/api/tasks?per_page=10');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'task_type', 'is_active', 'status'],
                ],
                'links' => ['first', 'last'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'server_time'],
                'accounts',
            ]);

        // POST /api/tasks -> 201 Created
        $storeResponse = $this->postJson('/api/tasks', [
            'account_id' => $account->id,
            'task_type' => 'collect_pickups',
            'schedule_type' => 'daily',
            'run_at_time' => '14:00',
            'payload' => ['pickup_type' => 'all'],
        ]);
        $storeResponse->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'task_type', 'schedule_type'],
                'meta' => ['server_time'],
            ]);

        $storeCollectBuildingResponse = $this->postJson('/api/tasks', [
            'account_id' => $account->id,
            'task_type' => 'collect_building',
            'schedule_type' => 'daily',
            'run_at_time' => '15:00',
            'payload' => ['grid' => 1234, 'mode' => 'auto'],
        ]);
        $storeCollectBuildingResponse->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'task_type', 'schedule_type'],
                'meta' => ['server_time'],
            ]);

        // POST /api/tasks/{id}/execute -> 202 Accepted
        $executeResponse = $this->postJson('/api/tasks/'.$task->id.'/execute');
        $executeResponse->assertStatus(202)
            ->assertJsonStructure([
                'data' => ['id', 'task_type'],
                'queued',
                'meta' => ['server_time'],
            ]);

        // DELETE /api/tasks/{id} -> 204 No Content
        $deleteResponse = $this->deleteJson('/api/tasks/'.$task->id);
        $deleteResponse->assertNoContent();
    }

    public function test_logs_endpoint_contract_and_pagination(): void
    {
        // GET /api/logs -> 200 OK with paginated structure & meta.server_time
        $response = $this->getJson('/api/logs?per_page=15');
        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total', 'server_time'],
                'accounts',
            ]);
    }

    public function test_settings_endpoints_contract(): void
    {
        // GET /api/settings -> 200 OK
        $response = $this->getJson('/api/settings');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['sync_interval', 'log_retention_days'],
            ]);

        // DELETE /api/settings/logs -> 204 No Content
        $clearResponse = $this->deleteJson('/api/settings/logs');
        $clearResponse->assertNoContent();

        // POST /api/settings/tasks/stop -> 204 No Content
        $stopResponse = $this->postJson('/api/settings/tasks/stop');
        $stopResponse->assertNoContent();
    }
}
