<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Account;
use App\Models\MarketSyncLog;
use App\Services\MarketSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MarketSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [30, 120, 300];

    public Account $account;

    /**
     * Create a new job instance.
     */
    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->onQueue('tso-market');
    }

    /**
     * Execute the job.
     */
    public function handle(MarketSyncService $syncService): void
    {
        try {
            Log::info("Running MarketSyncJob for account #{$this->account->id}");
            $syncService->sync($this->account);
        } catch (Throwable $e) {
            Log::error("MarketSyncJob failed for account #{$this->account->id}: {$e->getMessage()}");
            throw $e;
        } finally {
            Cache::forget("market_sync_lock:{$this->account->id}");
        }
    }

    /**
     * Handle job failure after all retries exhausted.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("MarketSyncJob failed permanently for account #{$this->account->id}: {$exception->getMessage()}");

        MarketSyncLog::create([
            'account_id' => $this->account->id,
            'action' => 'Sync Market',
            'status' => 'ERROR',
            'message' => "Market sync job failed permanently: {$exception->getMessage()}",
        ]);

        Cache::forget("market_sync_lock:{$this->account->id}");
    }
}
