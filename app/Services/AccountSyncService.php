<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Services\Account\Sync\AccountSyncFetcher;
use App\Services\Account\Sync\AccountSyncLogger;
use App\Services\Account\Sync\AccountSyncPersister;
use Exception;

/**
 * High-level orchestrator for Account synchronization pipeline.
 */
class AccountSyncService
{
    public function __construct(
        private readonly AccountSyncFetcher $fetcher,
        private readonly AccountSyncPersister $persister,
        private readonly AccountSyncLogger $logger,
    ) {}

    /**
     * Sync: authenticate, fetch zone data, persist to account, and log results.
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function sync(Account $account): array
    {
        try {
            $this->logger->logStart($account);
            $this->persister->markSyncing($account);

            $zoneData = $this->fetcher->fetchZone($account);

            $this->persister->saveSuccess($account, $zoneData);
            $this->logger->logSuccess($account, $zoneData);

            return $zoneData;
        } catch (Exception $e) {
            $this->persister->markError($account);
            $this->logger->logFailure($account, $e);

            throw $e;
        }
    }
}
