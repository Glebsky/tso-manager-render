<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\BotLog;
use App\Models\MarketHistory;
use App\Models\MarketOffer;
use App\Models\MarketServerConnection;
use App\Models\MarketSyncLog;
use App\Services\Lang\GameTranslationResolver;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarketSyncService
{
    private TsoAuthService $authService;

    private TsoAmfService $amfService;

    private GameTranslationResolver $gameTranslations;

    public function __construct(TsoAuthService $authService, TsoAmfService $amfService, GameTranslationResolver $gameTranslations)
    {
        $this->authService = $authService;
        $this->amfService = $amfService;
        $this->gameTranslations = $gameTranslations;
    }

    private function findPython(): string
    {
        $candidates = ['python', 'python3', 'py'];
        foreach ($candidates as $bin) {
            $out = [];
            $code = 0;
            exec(escapeshellarg($bin).' --version 2>&1', $out, $code);
            if ($code === 0) {
                return $bin;
            }
        }
        throw new Exception('Python not found in system PATH.');
    }

    public function sync(Account $account, ?string $serverId = null): array
    {
        $action = 'Market synchronized';
        $collectedAt = now();

        if (empty($serverId)) {
            $connection = MarketServerConnection::where('account_id', $account->id)->first();
            $serverId = $connection ? $connection->server_id : strtolower((string) ($account->region ?? 'ru'));
        }

        $connection = MarketServerConnection::where('server_id', $serverId)->first();
        if ($connection) {
            $connection->update(['sync_status' => 'syncing']);
        }

        try {
            $this->logEvent($account, $action, 'INFO', "Starting market synchronization for server [{$serverId}]", $serverId);

            // 1. Authenticate if needed
            if (! $this->authService->isAuthenticated($account)) {
                $this->authService->login($account);
                $account->refresh();
            }

            // 2. Fetch and parse market updates with retry loop (handles zone loading)
            $maxRetries = 6;
            $retryDelay = 3; // seconds
            $hasResetSession = false;
            $parsed = null;
            $errorCode = 0;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $this->logEvent($account, $action, 'INFO', "Fetching market offers AMF (attempt {$attempt}/{$maxRetries})", $serverId);
                    $rawAmf = $this->amfService->getMarketOffers($account);

                    $scriptPath = storage_path('app/parse_market.py');
                    if (! file_exists($scriptPath)) {
                        throw new Exception('parse_market.py not found in storage/app/');
                    }

                    $tmpFile = storage_path('app/temp_market_'.uniqid().'.amf');
                    file_put_contents($tmpFile, $rawAmf);

                    try {
                        $pythonBin = $this->findPython();
                        $command = escapeshellarg($pythonBin).' '.escapeshellarg($scriptPath).' '.escapeshellarg($tmpFile);
                        $output = [];
                        $exitCode = 0;

                        exec($command.' 2>&1', $output, $exitCode);
                        $outputStr = implode("\n", $output);

                        if ($exitCode !== 0) {
                            throw new Exception("parse_market.py failed: {$outputStr}");
                        }

                        $parsed = json_decode($outputStr, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new Exception('Failed to decode JSON from parser: '.json_last_error_msg());
                        }
                    } finally {
                        @unlink($tmpFile);
                    }

                    $errorCode = $parsed['errorCode'] ?? 0;

                    if ($errorCode === 1012) {
                        $this->logEvent($account, $action, 'WARNING', "Received error 1012 (Zone loading). Waiting {$retryDelay}s and retrying...", $serverId);
                        sleep($retryDelay);

                        continue;
                    }

                    if ($errorCode === 1005) {
                        if ($hasResetSession) {
                            throw new Exception(__('ui.sync.session_intercepted_market', ['code' => $errorCode]));
                        }
                        $this->logEvent($account, $action, 'WARNING', "Received error {$errorCode} (Session expired). Resetting session...", $serverId);
                        @unlink($this->authService->getCookieFile($account));
                        $this->authService->login($account);
                        $this->amfService->resetClient();
                        $account->refresh();
                        $hasResetSession = true;
                        sleep(2);

                        continue;
                    }

                    // Success or other unhandled code
                    break;
                } catch (Exception $attemptEx) {
                    $this->logEvent($account, $action, 'WARNING', "Attempt {$attempt}/{$maxRetries} failed: ".$attemptEx->getMessage(), $serverId);
                    if ($attempt === $maxRetries) {
                        throw $attemptEx;
                    }
                    sleep($retryDelay);
                }
            }

            if ($errorCode !== 0) {
                throw new Exception("Server returned error code {$errorCode} during market sync.");
            }

            $rawOffers = $parsed['offers'] ?? [];

            $offersToInsert = [];
            $historyToInsert = [];

            foreach ($rawOffers as $raw) {
                $offerStr = $raw['offer'] ?? '';
                if (empty($offerStr)) {
                    continue;
                }

                $parts = explode('|', $offerStr);
                if (count($parts) < 2) {
                    continue;
                }

                // Parse Selling Item
                $sellParts = explode(',', $parts[0]);
                if (count($sellParts) < 2) {
                    continue;
                }
                $itemId = $sellParts[0];
                $amount = (int) $sellParts[1];

                // Parse Target Item
                if ($parts[1] === '@') {
                    // No target item (free or direct gift)
                    continue;
                }
                $targetParts = explode(',', $parts[1]);
                if (count($targetParts) < 2) {
                    continue;
                }
                $targetItemId = $targetParts[0];
                $targetAmount = (int) $targetParts[1];

                if ($amount <= 0 || $targetAmount <= 0) {
                    continue;
                }

                $price = (float) $targetAmount / $amount;
                $lotsRemaining = (int) ($raw['lotsRemaining'] ?? 1);
                $volume = $amount * $lotsRemaining;

                // Human-readable names
                $itemName = $this->gameTranslations->name('RES', $itemId);
                $targetItemName = $this->gameTranslations->name('RES', $targetItemId);

                // Game created timestamp (created is in ms)
                $gameCreatedMs = $raw['created'] ?? 0;
                $gameCreatedAt = $gameCreatedMs > 0 ? date('Y-m-d H:i:s', (int) ($gameCreatedMs / 1000)) : $collectedAt;

                $offerId = (int) ($raw['id'] ?? 0);
                if ($offerId <= 0) {
                    continue;
                }

                $offerData = [
                    'server_id' => $serverId,
                    'offer_id' => $offerId,
                    'player_id' => (int) $raw['senderID'],
                    'sender_name' => $raw['senderName'] ?? 'Unknown',
                    'item_id' => $itemId,
                    'item_name' => $itemName,
                    'amount' => $amount,
                    'target_item_id' => $targetItemId,
                    'target_item_name' => $targetItemName,
                    'target_amount' => $targetAmount,
                    'price' => $price,
                    'volume' => $volume,
                    'lots_remaining' => $lotsRemaining,
                    'created_at' => $gameCreatedAt,
                    'collected_at' => $collectedAt,
                ];

                $offersToInsert[$offerId] = $offerData;

                $historyData = [
                    'server_id' => $serverId,
                    'offer_id' => $offerId,
                    'player_id' => (int) $raw['senderID'],
                    'item_id' => $itemId,
                    'item_name' => $itemName,
                    'amount' => $amount,
                    'target_item_id' => $targetItemId,
                    'target_item_name' => $targetItemName,
                    'target_amount' => $targetAmount,
                    'price' => $price,
                    'volume' => $volume,
                    'collected_at' => $collectedAt,
                ];

                $historyToInsert[$offerId] = $historyData;
            }

            $offersToInsertList = array_values($offersToInsert);
            $historyToInsertList = array_values($historyToInsert);

            // 5. Database updates isolated by server_id
            DB::transaction(function () use ($serverId, $offersToInsertList, $historyToInsertList) {
                // Clear active offers for this server ONLY
                MarketOffer::where('server_id', $serverId)->delete();

                // Chunk inserts to avoid database limits
                foreach (array_chunk($offersToInsertList, 200) as $chunk) {
                    MarketOffer::insert($chunk);
                }

                // Filter out history entries that already exist for this server
                $offerIds = array_column($historyToInsertList, 'offer_id');
                $existingIds = [];
                if (! empty($offerIds)) {
                    foreach (array_chunk($offerIds, 500) as $idChunk) {
                        $chunkExisting = MarketHistory::where('server_id', $serverId)
                            ->whereIn('offer_id', $idChunk)
                            ->pluck('offer_id')
                            ->toArray();
                        $existingIds = array_merge($existingIds, $chunkExisting);
                    }
                }

                $existingIdsSet = array_flip($existingIds);
                $filteredHistory = [];
                foreach ($historyToInsertList as $h) {
                    if (! isset($existingIdsSet[$h['offer_id']])) {
                        $filteredHistory[] = $h;
                    }
                }

                if (! empty($filteredHistory)) {
                    foreach (array_chunk($filteredHistory, 200) as $chunk) {
                        MarketHistory::insert($chunk);
                    }
                }
            });

            $count = count($offersToInsertList);
            $message = "{$count} offers received for server [{$serverId}]";

            $this->logEvent($account, $action, 'SUCCESS', $message, $serverId);

            if ($connection) {
                $connection->update([
                    'sync_status' => 'connected',
                    'last_synced_at' => now(),
                    'last_error' => null,
                ]);
            }

            return [
                'success' => true,
                'message' => $message,
                'count' => $count,
                'server_id' => $serverId,
            ];

        } catch (Exception $e) {
            $this->logEvent($account, $action, 'ERROR', $e->getMessage(), $serverId);

            if ($connection) {
                $connection->update([
                    'sync_status' => 'error',
                    'last_error' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    private function logEvent(Account $account, string $action, string $status, string $message, ?string $serverId = null): void
    {
        // 1. Write to standard Laravel file logs (storage/logs/laravel.log)
        $logMessage = "[MarketSync] [{$account->username}] [server:{$serverId}] {$action} - {$status}: {$message}";
        if ($status === 'FAILED' || $status === 'ERROR') {
            Log::error($logMessage);
        } elseif ($status === 'WARNING') {
            Log::warning($logMessage);
        } else {
            Log::info($logMessage);
        }

        // 2. Write to the database table (MarketSyncLog model) and BotLog
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

            BotLog::create([
                'account_id' => $account->id,
                'level' => $botLogLevel,
                'message' => "[Market][{$serverId}] {$action}: {$message}",
                'created_at' => now(),
            ]);
        } catch (Exception $dbEx) {
            Log::error('Failed to write market sync log to database: '.$dbEx->getMessage());
        }
    }
}
