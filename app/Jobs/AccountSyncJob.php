<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Account;
use App\Models\BotLog;
use App\Services\AccountSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AccountSyncJob implements ShouldQueue
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
        $this->onQueue('tso-tasks');
    }

    /**
     * Execute the job.
     */
    public function handle(AccountSyncService $syncService): void
    {
        try {
            Log::info("Running AccountSyncJob for account #{$this->account->id}");
            $syncService->sync($this->account);
        } catch (Throwable $e) {
            Log::error("AccountSyncJob failed for account #{$this->account->id}: {$e->getMessage()}");
            throw $e;
        } finally {
            Cache::lock("account_sync_lock:{$this->account->id}")->forceRelease();
        }
    }

    /**
     * Handle job failure after all retries exhausted.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("AccountSyncJob failed permanently for account #{$this->account->id}: {$exception->getMessage()}");

        BotLog::create([
            'account_id' => $this->account->id,
            'level' => 'error',
            'message' => "Account sync job failed permanently: {$exception->getMessage()}",
        ]);

        Cache::lock("account_sync_lock:{$this->account->id}")->forceRelease();
    }
}
