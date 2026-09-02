<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\MarketServerConnection;
use App\Services\Market\Sync\MarketOfferFetcher;
use App\Services\Market\Sync\MarketOfferParser;
use App\Services\Market\Sync\MarketOfferPersister;
use App\Services\Market\Sync\MarketSyncLogger;
use Exception;
use Throwable;

/**
 * High-level orchestrator for market data synchronization.
 */
readonly class MarketSyncService
{
    public function __construct(
        private MarketOfferFetcher $fetcher,
        private MarketOfferParser $parser,
        private MarketOfferPersister $persister,
        private MarketSyncLogger $syncLogger,
        private MarketCacheService $cacheService,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws Exception
     * @throws Throwable
     */
    public function sync(Account $account, ?string $serverId = null, ?MarketServerConnection $connection = null): array
    {
        $action = 'Market sync';
        $collectedAt = now();

        if (! $connection) {
            if (empty($serverId)) {
                $connection = MarketServerConnection::where('account_id', $account->id)->first();
                $serverId = $connection->server_id ?? strtolower($account->region ?? 'ru');
            }
            $connection = MarketServerConnection::where('server_id', $serverId)->first();
        } else {
            $serverId = $connection->server_id;
        }

        $connection?->update(['sync_status' => 'syncing']);

        try {
            $this->syncLogger->log($account, $action, 'INFO', __('logs.market.sync_started', ['server' => $serverId]), $serverId);

            $parsed = $this->fetcher->fetch($account, function (string $status, string $message) use ($account, $action, $serverId): void {
                $this->syncLogger->log($account, $action, $status, $message, $serverId);
            });

            $rawOffers = is_array($parsed['offers'] ?? null) ? $parsed['offers'] : [];
            $parsedData = $this->parser->parse($rawOffers, $serverId, $collectedAt);

            $this->persister->persist($serverId, $parsedData['offers'], $parsedData['history']);

            $count = count($parsedData['offers']);
            $unparsed = $parsedData['unparsed_offers'];
            $message = __('logs.market.sync_success', ['count' => $count, 'server' => $serverId]);
            if ($unparsed > 0) {
                $message .= " (skipped {$unparsed} unparsed)";
            }

            $this->syncLogger->log($account, $action, 'SUCCESS', $message, $serverId);

            $connection?->update([
                'sync_status' => 'connected',
                'last_synced_at' => now(),
                'last_error' => null,
                'data_version' => ($connection->data_version ?? 0) + 1,
            ]);

            $this->cacheService->invalidateVersionCache($serverId);

            return [
                'success' => true,
                'message' => $message,
                'count' => $count,
                'server_id' => $serverId,
            ];
        } catch (Exception $e) {
            $this->syncLogger->log($account, $action, 'ERROR', __('logs.market.sync_failed', ['error' => $e->getMessage()]), $serverId);

            $connection?->update([
                'sync_status' => 'error',
                'last_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
