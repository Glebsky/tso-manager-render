<?php

namespace App\Jobs;

use App\Models\Account;
use App\Services\MarketSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MarketSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Account $account;

    /**
     * Create a new job instance.
     */
    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    /**
     * Execute the job.
     */
    public function handle(MarketSyncService $syncService): void
    {
        try {
            Log::info("Running MarketSyncJob for account #{$this->account->id}");
            $syncService->sync($this->account);
        } catch (\Exception $e) {
            Log::error('MarketSyncJob failed: '.$e->getMessage());
            // Re-throw to fail the job in queue
            throw $e;
        }
    }
}
