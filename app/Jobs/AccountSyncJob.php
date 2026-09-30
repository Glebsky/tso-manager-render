<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\UnrecoverableAuthError;
use App\Models\Account;
use App\Services\AccountSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One sync per account at a time, guaranteed by ShouldBeUnique alone (A-13):
 * the unique lock is taken at dispatch and released by the queue after the
 * job succeeds or fails for good, so it survives retries — unlike the former
 * hand-rolled cache lock that every attempt released in `finally`.
 */
class AccountSyncJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    /**
     * Must cover the whole retry window (3 × timeout + all backoffs = 810 s),
     * otherwise a second sync could be queued while the first is still retrying.
     * Verified by SyncJobUniquenessTest.
     */
    public int $uniqueFor = 1200;

    public Account $account;

    /**
     * Create a new job instance.
     */
    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->onQueue(config('game.queues.accounts'));
    }

    public function uniqueId(): string
    {
        return (string) $this->account->id;
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'account:'.$this->account->id,
            'account_name:'.$this->account->username,
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(AccountSyncService $syncService): void
    {
        try {
            Log::info("[AccountSyncJob] Started for account #{$this->account->id} ({$this->account->username})");
            $syncService->sync($this->account);
        } catch (Throwable $e) {
            Log::error("[AccountSyncJob] Failed for account #{$this->account->id}: {$e->getMessage()}");

            if (UnrecoverableAuthError::isUnrecoverable($e)) {
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
        Log::error("[AccountSyncJob] Failed permanently for account #{$this->account->id} after all retries: {$exception->getMessage()}");
    }
}
