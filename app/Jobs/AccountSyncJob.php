<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\LogLevel;
use App\Models\Account;
use App\Models\BotLog;
use App\Services\AccountSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class AccountSyncJob implements ShouldQueue, ShouldBeUnique
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
        $this->onQueue('tso-accounts');
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
            'account:' . $this->account->id,
            'account_name:' . $this->account->username,
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

            if ($this->isUnrecoverableAuthError($e->getMessage())) {
                $this->account->update(['status' => 'session_expired']);
                BotLog::create([
                    'account_id' => $this->account->id,
                    'level' => LogLevel::Error,
                    'message' => '[AccountSync] '.__('logs.account.sync_job_failed', ['error' => $e->getMessage()]),
                ]);

                return;
            }

            throw $e;
        } finally {
            Cache::forget("account_sync_lock:{$this->account->id}");
        }
    }

    private function isUnrecoverableAuthError(string $message): bool
    {
        return (bool) preg_match('/captcha|2fa|twofactor|session_expired|session expired/i', $message);
    }

    /**
     * Handle job failure after all retries exhausted.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("[AccountSyncJob] Failed permanently for account #{$this->account->id} after all retries: {$exception->getMessage()}");

        BotLog::create([
            'account_id' => $this->account->id,
            'level' => LogLevel::Error,
            'message' => '[AccountSync] '.__('logs.account.sync_job_failed', ['error' => $exception->getMessage()]),
        ]);

        Cache::forget("account_sync_lock:{$this->account->id}");
    }
}
