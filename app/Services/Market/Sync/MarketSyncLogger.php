<?php

declare(strict_types=1);

namespace App\Services\Market\Sync;

use App\Models\Account;
use App\Models\BotLog;
use App\Models\MarketSyncLog;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Service responsible for logging market synchronization events to file and database tables.
 */
class MarketSyncLogger
{
    public function log(Account $account, string $action, string $status, string $message, ?string $serverId = null): void
    {
        $logMessage = "[MarketSync][server:{$serverId}][{$account->username}] {$status}: {$message}";
        if ($status === 'FAILED' || $status === 'ERROR') {
            Log::error($logMessage);
        } elseif ($status === 'WARNING') {
            Log::warning($logMessage);
        } else {
            Log::info($logMessage);
        }

        if (strtoupper($status) === 'INFO') {
            return;
        }

        try {
            MarketSyncLog::create([
                'account_id' => $account->id,
                'server_id' => $serverId,
                'action' => $action,
                'status' => $status,
                'message' => $message,
                'created_at' => now(),
            ]);

            $botLogLevel = match (strtoupper($status)) {
                'FAILED', 'ERROR' => 'error',
                'WARNING' => 'warning',
                'SUCCESS' => 'success',
                default => 'info',
            };

            $serverTag = $serverId ? "[Market][{$serverId}]" : '[Market]';

            BotLog::create([
                'account_id' => $account->id,
                'level' => $botLogLevel,
                'message' => "{$serverTag} {$message}",
                'created_at' => now(),
            ]);
        } catch (Exception $dbEx) {
            Log::error("[MarketSync] Failed to write sync log to database: {$dbEx->getMessage()}");
        }
    }
}
