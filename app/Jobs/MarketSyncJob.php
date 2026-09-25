<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AccountStatus;
use App\Enums\LogLevel;
use App\Enums\MarketSyncStatus;
use App\Exceptions\UnrecoverableAuthError;
use App\Models\Account;
use App\Models\BotLog;
use App\Models\MarketServerConnection;
use App\Models\MarketSyncLog;
use App\Services\MarketSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One market sync per game server at a time, guaranteed by ShouldBeUnique
 * (A-13). The unique lock is held across retries and released by the queue
 * only after success or permanent failure — the former cache lock was
 * released in `finally` on every attempt, so a retrying sync could be
 * duplicated by the next scheduler tick.
 */
class MarketSyncJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * Must cover the whole retry window (3 × timeout + all backoffs = 810 s).
     * Verified by SyncJobUniquenessTest.
     */
    public int $uniqueFor = 1200;

    public Account $account;

    public string $serverId;

    /**
     * Create a new job instance.
     */
    public function __construct(Account $account, string $serverId = 'ru')
    {
        $this->account = $account;
        $this->serverId = $serverId;
        $this->onQueue(config('game.queues.market'));
    }

    public function uniqueId(): string
    {
        return $this->serverId;
    }

    /**
     * Execute the job.
     *
     * @throws Throwable
     */
    public function handle(MarketSyncService $syncService): void
    {
        try {
            Log::info("[MarketSyncJob] Started for account #{$this->account->id} on server [{$this->serverId}]");
            $syncService->sync($this->account, $this->serverId);
        } catch (Throwable $e) {
            Log::error("[MarketSyncJob] Failed for account #{$this->account->id} on server [{$this->serverId}]: {$e->getMessage()}");

            if (UnrecoverableAuthError::isUnrecoverable($e)) {
                $this->account->update(['status' => AccountStatus::SessionExpired->value]);
                MarketServerConnection::where('server_id', $this->serverId)->update([
                    'sync_status' => MarketSyncStatus::Error->value,
                    'last_error' => $e->getMessage(),
                ]);
                BotLog::create([
                    'account_id' => $this->account->id,
                    'level' => LogLevel::Error,
                    'message' => "[Market][{$this->serverId}] ".__('logs.market.sync_job_failed', ['error' => $e->getMessage()]),
                    'created_at' => now(),
                ]);

                $this->fail($e);

                return;
            }

            throw $e;
        }
    }

    /**
     * Handle job failure after all retries exhausted.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("[MarketSyncJob] Failed permanently for account #{$this->account->id} on server [{$this->serverId}] after all retries: {$exception->getMessage()}");

        MarketSyncLog::create([
            'account_id' => $this->account->id,
            'server_id' => $this->serverId,
            'action' => 'Market sync',
            'status' => 'ERROR',
            'message' => __('logs.market.sync_job_failed', ['error' => $exception->getMessage()]),
            'created_at' => now(),
        ]);

        try {
            MarketServerConnection::where('server_id', $this->serverId)->update([
                'sync_status' => MarketSyncStatus::Error->value,
                'last_error' => $exception->getMessage(),
            ]);

            BotLog::create([
                'account_id' => $this->account->id,
                'level' => LogLevel::Error,
                'message' => "[Market][{$this->serverId}] ".__('logs.market.sync_job_failed', ['error' => $exception->getMessage()]),
                'created_at' => now(),
            ]);
        } catch (Throwable $dbEx) {
            Log::error("[MarketSyncJob] Failed to write market sync failure to the activity log: {$dbEx->getMessage()}");
        }
    }
}
