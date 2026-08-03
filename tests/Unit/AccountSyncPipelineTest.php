<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Account;
use App\Services\Account\Sync\AccountSyncFetcher;
use App\Services\Account\Sync\AccountSyncLogger;
use App\Services\Account\Sync\AccountSyncPersister;
use App\Services\AccountSyncService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSyncPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_persister_updates_account_status_and_zone_data(): void
    {
        $account = Account::create([
            'username' => 'pipeline_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'pipeline_user',
        ]);

        $persister = new AccountSyncPersister;

        $persister->markSyncing($account);
        $this->assertEquals('syncing', $account->fresh()->status);

        $zoneData = ['userID' => 123, 'buildings' => [['id' => 1]]];
        $persister->saveSuccess($account, $zoneData);

        $account->refresh();
        $this->assertEquals('online', $account->status);
        $this->assertNotNull($account->last_sync_at);
        $this->assertStringContainsString('"userID":123', $account->zone_data);

        $persister->markError($account);
        $this->assertEquals('error', $account->fresh()->status);
    }

    public function test_logger_creates_bot_logs(): void
    {
        $account = Account::create([
            'username' => 'logger_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'logger_user',
        ]);

        $logger = new AccountSyncLogger;

        $logger->logSuccess($account, [
            'buildings' => [1, 2],
            'resources' => [10],
            'specialists' => [],
            'buffs' => [],
        ]);

        $this->assertDatabaseHas('bot_logs', [
            'account_id' => $account->id,
            'level' => 'success',
        ]);

        $logger->logFailure($account, new Exception('Sync error test'));

        $this->assertDatabaseHas('bot_logs', [
            'account_id' => $account->id,
            'level' => 'error',
        ]);
    }

    public function test_orchestrator_runs_pipeline_successfully(): void
    {
        $account = Account::create([
            'username' => 'orch_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'orch_user',
        ]);

        $fetcher = $this->createMock(AccountSyncFetcher::class);
        $fetcher->expects($this->once())
            ->method('fetchZone')
            ->willReturn(['buildings' => [['id' => 1]]]);

        $persister = new AccountSyncPersister;
        $logger = new AccountSyncLogger;

        $orchestrator = new AccountSyncService($fetcher, $persister, $logger);

        $result = $orchestrator->sync($account);

        $this->assertArrayHasKey('buildings', $result);
        $this->assertEquals('online', $account->fresh()->status);
    }
}
