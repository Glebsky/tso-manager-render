<?php

declare(strict_types=1);

namespace App\Services\Account\Sync;

use App\Enums\AccountStatus;
use App\Exceptions\UnrecoverableAuthError;
use App\Models\Account;
use App\Support\Zone\ZoneSnapshot;
use Throwable;

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
        $account->update(['status' => AccountStatus::Syncing->value]);
    }

    /**
     * Update account with successfully fetched zone data and set status to online.
     *
     * @param  array<string, mixed>  $zoneData
     */
    public function saveSuccess(Account $account, array $zoneData): void
    {
        $snapshot = ZoneSnapshot::fromData($zoneData);

        $account->update([
            'zone_data' => json_encode($zoneData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'avatar_id' => $snapshot->avatarId(),
            'building_count' => $snapshot->buildingCount(),
            'game_world_name' => $snapshot->serverName(),
            'last_sync_at' => now(),
            'status' => AccountStatus::Online->value,
        ]);
    }

    /**
     * Mark account status as error, or session_expired when the failure
     * needs a human (CAPTCHA / 2FA / rejected session — decided by exception
     * type, see UnrecoverableAuthError).
     */
    public function markError(Account $account, ?Throwable $exception = null): void
    {
        $targetStatus = $exception !== null && UnrecoverableAuthError::isUnrecoverable($exception)
            ? AccountStatus::SessionExpired->value
            : AccountStatus::Error->value;

        if ($account->status !== $targetStatus) {
            $account->update(['status' => $targetStatus]);
        }
    }
}
