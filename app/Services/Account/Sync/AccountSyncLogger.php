<?php

declare(strict_types=1);

namespace App\Services\Account\Sync;

use App\Enums\LogLevel;
use App\Models\Account;
use App\Models\BotLog;
use App\Support\Security\CredentialRedactor;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles structured BotLog and system log recording during account synchronization.
 */
class AccountSyncLogger
{
    /**
     * Log start of sync operation.
     */
    public function logStart(Account $account): void
    {
        Log::info("[AccountSync] Started for account #{$account->id} ({$account->username})");
    }

    /**
     * Log successful completion of account sync.
     *
     * @param  array<string, mixed>  $zoneData
     */
    public function logSuccess(Account $account, array $zoneData): void
    {
        $buildingCount = count($zoneData['buildings'] ?? []);
        $specialistCount = count($zoneData['specialists'] ?? []);
        $buffCount = count($zoneData['buffs'] ?? []);
        $resourceCount = count($zoneData['resources'] ?? []);

        Log::info("[AccountSync] Finished for account #{$account->id}: level ".($zoneData['level'] ?? 'N/A').', server '.($zoneData['gameWorldName'] ?? 'N/A').", buildings {$buildingCount}, resources {$resourceCount}");

        BotLog::create([
            'account_id' => $account->id,
            'level' => LogLevel::Success,
            'message' => '[AccountSync] '.__('logs.account.sync_success', [
                'buildings' => $buildingCount,
                'resources' => $resourceCount,
                'specialists' => $specialistCount,
                'buffs' => $buffCount,
            ]),
        ]);
    }

    /**
     * Log failure of account sync.
     */
    public function logFailure(Account $account, Throwable $exception): void
    {
        $redactedMessage = CredentialRedactor::redact($exception->getMessage(), $account);

        Log::error("[AccountSync] Failed for account #{$account->id}: ".$redactedMessage);

        BotLog::create([
            'account_id' => $account->id,
            'level' => LogLevel::Error,
            'message' => '[AccountSync] '.__('logs.account.sync_failed', ['error' => $redactedMessage]),
        ]);
    }
}
