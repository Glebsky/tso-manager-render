<?php

namespace App\Services;

use App\Models\Account;
use App\Models\MarketOffer;
use App\Models\MarketHistory;
use App\Models\MarketSyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class MarketSyncService
{
    private TsoAuthService    $authService;
    private TsoAmfService     $amfService;
    private LangParserService $langParser;

    public function __construct(TsoAuthService $authService, TsoAmfService $amfService, LangParserService $langParser)
    {
        $this->authService = $authService;
        $this->amfService  = $amfService;
        $this->langParser  = $langParser;
    }

    private function findPython(): string
    {
        $candidates = ['python', 'python3', 'py'];
        foreach ($candidates as $bin) {
            $out  = [];
            $code = 0;
            exec(escapeshellarg($bin) . ' --version 2>&1', $out, $code);
            if ($code === 0) {
                return $bin;
            }
        }
        throw new Exception('Python not found in system PATH.');
    }

    public function sync(Account $account): array
    {
        $action      = 'Market synchronized';
        $collectedAt = now();

        try {
            Log::info("Starting market sync for account {$account->id} ({$account->username})");

            // 1. Authenticate if needed
            if (!$this->authService->isAuthenticated($account)) {
                $this->authService->login($account);
                $account->refresh();
            }

            // 2. Fetch market updates AMF
            $rawAmf = $this->amfService->getMarketOffers($account);

            // 3. Shell out to Python parser
            $scriptPath = storage_path('app/parse_market.py');
            if (!file_exists($scriptPath)) {
                throw new Exception('parse_market.py not found in storage/app/');
            }

            $tmpFile = storage_path('app/temp_market_' . uniqid() . '.amf');
            file_put_contents($tmpFile, $rawAmf);

            try {
                $pythonBin = $this->findPython();
                $command   = escapeshellarg($pythonBin) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($tmpFile);
                $output    = [];
                $exitCode  = 0;

                exec($command . ' 2>&1', $output, $exitCode);
                $outputStr = implode("\n", $output);

                if ($exitCode !== 0) {
                    throw new Exception("parse_market.py failed: {$outputStr}");
                }

                $parsed = json_decode($outputStr, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Failed to decode JSON from parser: " . json_last_error_msg());
                }
            } finally {
                @unlink($tmpFile);
            }

            $rawOffers   = $parsed['offers'] ?? [];

            // 4. Translate resource names
            $translations = $this->langParser->getResTranslations();

            $offersToInsert  = [];
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

                $price         = (double) $targetAmount / $amount;
                $lotsRemaining = (int) ($raw['lotsRemaining'] ?? 1);
                $volume        = $amount * $lotsRemaining;

                // Human-readable names
                $itemName       = $translations[$itemId] ?? $itemId;
                $targetItemName = $translations[$targetItemId] ?? $targetItemId;

                // Game created timestamp (created is in ms)
                $gameCreatedMs = $raw['created'] ?? 0;
                $gameCreatedAt = $gameCreatedMs > 0 ? date('Y-m-d H:i:s', (int)($gameCreatedMs / 1000)) : $collectedAt;

                $offerData = [
                    'offer_id'         => (int) $raw['id'],
                    'player_id'        => (int) $raw['senderID'],
                    'sender_name'      => $raw['senderName'] ?? 'Unknown',
                    'item_id'          => $itemId,
                    'item_name'        => $itemName,
                    'amount'           => $amount,
                    'target_item_id'   => $targetItemId,
                    'target_item_name' => $targetItemName,
                    'target_amount'    => $targetAmount,
                    'price'            => $price,
                    'volume'           => $volume,
                    'lots_remaining'   => $lotsRemaining,
                    'created_at'       => $gameCreatedAt,
                    'collected_at'     => $collectedAt,
                ];

                $offersToInsert[] = $offerData;

                $historyData = [
                    'offer_id'         => (int) $raw['id'],
                    'player_id'        => (int) $raw['senderID'],
                    'item_id'          => $itemId,
                    'item_name'        => $itemName,
                    'amount'           => $amount,
                    'target_item_id'   => $targetItemId,
                    'target_item_name' => $targetItemName,
                    'target_amount'    => $targetAmount,
                    'price'            => $price,
                    'volume'           => $volume,
                    'collected_at'     => $collectedAt,
                ];

                $historyToInsert[] = $historyData;
            }

            // 5. Database updates
            DB::transaction(function () use ($offersToInsert, $historyToInsert) {
                // Clear active offers
                MarketOffer::truncate();

                // Chunk inserts to avoid database limits
                foreach (array_chunk($offersToInsert, 200) as $chunk) {
                    MarketOffer::insert($chunk);
                }

                foreach (array_chunk($historyToInsert, 200) as $chunk) {
                    MarketHistory::insert($chunk);
                }
            });

            $count   = count($offersToInsert);
            $message = "{$count} offers received";

            MarketSyncLog::create([
                'account_id' => $account->id,
                'action'     => $action,
                'status'     => 'SUCCESS',
                'message'    => $message,
                'created_at' => $collectedAt,
            ]);

            return [
                'success' => true,
                'message' => $message,
                'count'   => $count,
            ];

        } catch (Exception $e) {
            Log::error("Market sync failed for account {$account->id}: " . $e->getMessage());

            MarketSyncLog::create([
                'account_id' => $account->id,
                'action'     => $action,
                'status'     => 'ERROR',
                'message'    => $e->getMessage(),
                'created_at' => $collectedAt,
            ]);

            throw $e;
        }
    }
}
