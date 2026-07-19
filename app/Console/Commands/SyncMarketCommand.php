<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\MarketSyncJob;
use App\Models\Account;
use App\Models\MarketSyncLog;
use App\Services\MarketSyncService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SyncMarketCommand extends Command
{
    protected $signature = 'tso:sync-market {--sync : Execute synchronization synchronously instead of queueing}';

    protected $description = 'Trigger automatic market sync if the configured interval has elapsed.';

    private MarketSyncService $syncService;

    public function __construct(MarketSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    private function getSettingsPath(): string
    {
        return 'market_settings.json';
    }

    private function loadSettings(): array
    {
        if (Storage::disk('local')->exists($this->getSettingsPath())) {
            return json_decode(Storage::disk('local')->get($this->getSettingsPath()), true) ?? [];
        }

        return [
            'account_id' => null,
            'sync_interval' => '15',
            'custom_interval_minutes' => 15,
        ];
    }

    public function handle(): int
    {
        $settings = $this->loadSettings();
        $accountId = $settings['account_id'] ?? null;

        if (empty($accountId)) {
            $this->info('No account configured for Market Analytics. Skipping sync.');

            return self::SUCCESS;
        }

        $account = Account::find($accountId);
        if (! $account) {
            $this->error("Configured market account #{$accountId} not found.");

            return self::FAILURE;
        }

        // Determine target interval in minutes
        $interval = 15;
        $syncIntervalStr = (string) ($settings['sync_interval'] ?? '15');
        if ($syncIntervalStr === 'custom') {
            $interval = (int) ($settings['custom_interval_minutes'] ?? 15);
        } else {
            $interval = (int) $syncIntervalStr;
        }

        if ($interval <= 0) {
            $this->info('Market sync is disabled (interval = 0).');

            return self::SUCCESS;
        }

        // Check last successful sync log
        $lastLog = MarketSyncLog::where('account_id', $account->id)
            ->where('status', 'SUCCESS')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastLog) {
            $elapsedMinutes = Carbon::now()->diffInMinutes($lastLog->created_at);
            if ($elapsedMinutes < $interval) {
                $this->info("Last sync was {$elapsedMinutes} minutes ago. Configured interval: {$interval} minutes. Skipping.");

                return self::SUCCESS;
            }
        }

        $lockKey = "market_sync_lock:{$account->id}";
        $acquired = Cache::lock($lockKey, 180)->get();

        if (! $acquired) {
            $this->info("Market sync lock for account #{$account->id} already held. Skipping.");

            return self::SUCCESS;
        }

        if ($this->option('sync')) {
            $this->info("Running MarketSync synchronously for account [{$account->username}]...");
            try {
                $this->syncService->sync($account);
                $this->info('Market sync completed successfully.');
            } catch (Exception $e) {
                $this->error("Market sync failed: {$e->getMessage()}");

                return self::FAILURE;
            } finally {
                Cache::forget($lockKey);
            }
        } else {
            $this->info("Dispatching MarketSyncJob for account [{$account->username}]...");
            MarketSyncJob::dispatch($account);
        }

        return self::SUCCESS;
    }
}
