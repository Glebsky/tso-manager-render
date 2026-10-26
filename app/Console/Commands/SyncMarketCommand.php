<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AccountStatus;
use App\Enums\MarketSyncStatus;
use App\Jobs\MarketSyncJob;
use App\Models\MarketServerConnection;
use App\Models\MarketSyncLog;
use App\Models\Setting;
use App\Services\MarketSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncMarketCommand extends Command
{
    protected $signature = 'tso:sync-market {--sync : Execute synchronization synchronously instead of queueing} {--server= : Optional specific server_id to sync}';

    protected $description = 'Trigger automatic market sync for configured server connections if their interval has elapsed.';

    private MarketSyncService $syncService;

    public function __construct(MarketSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    private function getSyncIntervalMinutes(): int
    {
        if (config('game.market_sync_enabled', true) === false) {
            return 0;
        }

        $syncIntervalStr = (string) Setting::get('market_sync_interval', '15');
        if ($syncIntervalStr === 'custom') {
            return (int) Setting::get('market_custom_interval_minutes', 15);
        }

        return (int) $syncIntervalStr;
    }

    /**
     * @throws Throwable
     */
    public function handle(): int
    {
        $interval = $this->getSyncIntervalMinutes();
        if ($interval <= 0) {
            $this->info('Market sync is disabled (interval = 0).');

            return self::SUCCESS;
        }

        $specificServer = $this->option('server');
        $query = MarketServerConnection::with('account')
            ->whereNotNull('account_id')
            ->where('sync_status', '!=', MarketSyncStatus::Disabled->value);

        if ($specificServer) {
            $query->where('server_id', $specificServer);
        }

        $connections = $query->get();
        if ($connections->isEmpty()) {
            $this->info('No server connections configured with assigned accounts. Skipping sync.');

            return self::SUCCESS;
        }

        foreach ($connections as $connection) {
            $serverId = $connection->server_id;
            $account = $connection->account;

            if (! $account) {
                $this->error("Account #{$connection->account_id} for server [{$serverId}] not found.");
                $connection->update([
                    'sync_status' => MarketSyncStatus::Error->value,
                    'last_error' => "Configured account #{$connection->account_id} not found.",
                ]);

                continue;
            }

            if ($account->status === AccountStatus::SessionExpired->value || Cache::has("account_login_cooldown:{$account->id}")) {
                $this->warn("Account #{$account->id} ({$account->username}) for server [{$serverId}] requires manual session update or is under login cooldown. Skipping.");

                continue;
            }

            // Check elapsed time since last successful sync for this server
            $lastSyncTime = $connection->last_synced_at;
            if ($lastSyncTime === null) {
                $lastLog = MarketSyncLog::where('server_id', $serverId)
                    ->where('status', 'SUCCESS')
                    ->latest()
                    ->first();
                $lastSyncTime = $lastLog?->created_at;
            }

            if ($lastSyncTime) {
                $elapsedMinutes = (int) $lastSyncTime->diffInMinutes(Carbon::now());
                if ($elapsedMinutes < $interval) {
                    $this->info("Server [{$serverId}]: Last sync was {$elapsedMinutes} minutes ago. Configured interval: {$interval} minutes. Skipping.");

                    continue;
                }
            }

            try {
                if ($this->option('sync')) {
                    // Inline mode runs under the scheduler's withoutOverlapping().
                    $this->info("Running MarketSync synchronously for server [{$serverId}] account [{$account->username}]...");
                    $this->syncService->sync($account, $serverId);
                    $this->info("Market sync for server [{$serverId}] completed successfully.");
                } else {
                    // Deduplicated per server by MarketSyncJob's ShouldBeUnique (A-13).
                    $this->info("Dispatching MarketSyncJob for server [{$serverId}] account [{$account->username}]...");
                    MarketSyncJob::dispatch($account, $serverId);
                }
            } catch (Throwable $e) {
                $this->error("Market sync for server [{$serverId}] failed: {$e->getMessage()}");
                $connection->update([
                    'sync_status' => MarketSyncStatus::Error->value,
                    'last_error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
