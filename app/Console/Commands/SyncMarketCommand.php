<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\MarketSyncJob;
use App\Models\Account;
use App\Models\MarketServerConnection;
use App\Models\MarketSyncLog;
use App\Models\Setting;
use App\Services\MarketSyncService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

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
        $syncIntervalStr = (string) Setting::get('market_sync_interval', '15');
        if ($syncIntervalStr === 'custom') {
            return (int) Setting::get('market_custom_interval_minutes', 15);
        }

        return (int) $syncIntervalStr;
    }

    public function handle(): int
    {
        $interval = $this->getSyncIntervalMinutes();
        if ($interval <= 0) {
            $this->info('Market sync is disabled (interval = 0).');

            return self::SUCCESS;
        }

        $specificServer = $this->option('server');
        $query = MarketServerConnection::whereNotNull('account_id')
            ->where('sync_status', '!=', 'disabled');

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
            $account = Account::find($connection->account_id);

            if (! $account) {
                $this->error("Account #{$connection->account_id} for server [{$serverId}] not found.");
                $connection->update([
                    'sync_status' => 'error',
                    'last_error' => "Configured account #{$connection->account_id} not found.",
                ]);

                continue;
            }

            // Check elapsed time since last successful sync for this server
            $lastLog = MarketSyncLog::where('server_id', $serverId)
                ->where('status', 'SUCCESS')
                ->orderBy('created_at', 'desc')
                ->first();

            $lastSyncTime = $connection->last_synced_at ?? ($lastLog ? $lastLog->created_at : null);
            if ($lastSyncTime) {
                $elapsedMinutes = Carbon::now()->diffInMinutes($lastSyncTime);
                if ($elapsedMinutes < $interval) {
                    $this->info("Server [{$serverId}]: Last sync was {$elapsedMinutes} minutes ago. Configured interval: {$interval} minutes. Skipping.");

                    continue;
                }
            }

            $lockKey = "market_sync_lock:server:{$serverId}";
            $acquired = Cache::add($lockKey, true, 180);

            if (! $acquired) {
                $this->info("Market sync lock for server [{$serverId}] already held. Skipping.");

                continue;
            }

            try {
                if ($this->option('sync')) {
                    $this->info("Running MarketSync synchronously for server [{$serverId}] account [{$account->username}]...");
                    $this->syncService->sync($account, $serverId);
                    $this->info("Market sync for server [{$serverId}] completed successfully.");
                } else {
                    $this->info("Dispatching MarketSyncJob for server [{$serverId}] account [{$account->username}]...");
                    MarketSyncJob::dispatch($account, $serverId);
                }
            } catch (Exception $e) {
                $this->error("Market sync for server [{$serverId}] failed: {$e->getMessage()}");
                $connection->update([
                    'sync_status' => 'error',
                    'last_error' => $e->getMessage(),
                ]);
            } finally {
                if ($this->option('sync')) {
                    Cache::forget($lockKey);
                }
            }
        }

        return self::SUCCESS;
    }
}
