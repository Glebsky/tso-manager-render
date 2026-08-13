<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\LogLevel;
use App\Models\Account;
use App\Models\BotLog;
use App\Models\MarketServerConnection;
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

    public string $serverId;

    /**
     * Create a new job instance.
     */
    public function __construct(Account $account, string $serverId = 'ru')
    {
        $this->account = $account;
        $this->serverId = $serverId;
        $this->onQueue('tso-market');
    }

    /**
     * Execute the job.
     */
    public function handle(MarketSyncService $syncService): void
    {
        try {
            Log::info("[MarketSyncJob] Started for account #{$this->account->id} on server [{$this->serverId}]");
            $syncService->sync($this->account, $this->serverId);
        } catch (Throwable $e) {
            Log::error("[MarketSyncJob] Failed for account #{$this->account->id} on server [{$this->serverId}]: {$e->getMessage()}");

            if ($this->isUnrecoverableAuthError($e->getMessage())) {
                $this->account->update(['status' => 'session_expired']);
                MarketServerConnection::where('server_id', $this->serverId)->update([
                    'sync_status' => 'error',
                    'last_error' => $e->getMessage(),
                ]);
                BotLog::create([
                    'account_id' => $this->account->id,
                    'level' => LogLevel::Error,
                    'message' => "[Market][{$this->serverId}] ".__('logs.market.sync_job_failed', ['error' => $e->getMessage()]),
                    'created_at' => now(),
                ]);

                return;
            }

            throw $e;
        } finally {
            Cache::forget("market_sync_lock:server:{$this->serverId}");
            Cache::forget("market_sync_lock:{$this->account->id}");
        }
    }

    private function isUnrecoverableAuthError(string $message): bool
    {
        return str_contains($message, 'CAPTCHA') ||
            str_contains($message, 'captcha') ||
            str_contains($message, 'Captcha') ||
            str_contains($message, '2FA') ||
            str_contains($message, 'twoFactor') ||
            str_contains($message, 'session_expired') ||
            str_contains($message, 'Session expired');
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
        ]);

        try {
            MarketServerConnection::where('server_id', $this->serverId)->update([
                'sync_status' => 'error',
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

        Cache::forget("market_sync_lock:server:{$this->serverId}");
        Cache::forget("market_sync_lock:{$this->account->id}");
    }
}
