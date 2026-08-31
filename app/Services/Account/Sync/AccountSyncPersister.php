<?php

declare(strict_types=1);

namespace App\Services\Account\Sync;

use App\Models\Account;

/**
 * Handles updating Account database state during sync lifecycle (syncing, online, error, zone_data).
 */
class AccountSyncPersister
{
    /**
     * Mark account as syncing.
     */
    public function markSyncing(Account $account): void
    {
        $account->update(['status' => 'syncing']);
    }

    /**
     * Update account with successfully fetched zone data and set status to online.
     *
     * @param  array<string, mixed>  $zoneData
     */
    public function saveSuccess(Account $account, array $zoneData): void
    {
        $account->update([
            'zone_data' => json_encode($zoneData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'last_sync_at' => now(),
            'status' => 'online',
        ]);
    }

    /**
     * Mark account status as error or session_expired based on exception.
     */
    public function markError(Account $account, ?\Throwable $exception = null): void
    {
        $targetStatus = 'error';
        if ($exception && preg_match('/captcha|2fa|twofactor|session_expired|session expired|unauthorized/i', $exception->getMessage())) {
            $targetStatus = 'session_expired';
        }

        if ($account->status !== $targetStatus) {
            $account->update(['status' => $targetStatus]);
        }
    }
}
