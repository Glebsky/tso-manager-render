<?php

declare(strict_types=1);

namespace App\Services\Market\Sync;

use App\Services\Lang\GameTranslationResolver;
use Carbon\CarbonInterface;

/**
 * Service responsible for parsing raw market offer strings into structured data.
 */
readonly class MarketOfferParser
{
    public function __construct(
        private GameTranslationResolver $gameTranslations,
        private int $offerLifetimeHours = 6,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rawOffers
     * @return array{offers: list<array<string, mixed>>, history: list<array<string, mixed>>}
     */
    public function parse(array $rawOffers, string $serverId, CarbonInterface $collectedAt): array
    {
        $offersToInsert = [];
        $historyToInsert = [];

        $expirationThreshold = (int) $collectedAt->timestamp - ($this->offerLifetimeHours * 3600);

        foreach ($rawOffers as $raw) {
            $offerStr = (string) ($raw['offer'] ?? '');
            if ($offerStr === '') {
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

            $itemName = $this->gameTranslations->name('RES', $itemId);
            $targetItemName = $this->gameTranslations->name('RES', $targetItemId);

            $gameCreatedMs = is_numeric($raw['created'] ?? null) ? (int) $raw['created'] : 0;
            $gameCreatedSec = $gameCreatedMs > 0 ? (int) ($gameCreatedMs / 1000) : (int) $collectedAt->timestamp;
            $gameCreatedAt = date('Y-m-d H:i:s', $gameCreatedSec);

            $offerId = (int) ($raw['id'] ?? 0);
            if ($offerId <= 0) {
                continue;
            }

            // Only add to active offers if not already expired at collection time
            if ($gameCreatedSec > $expirationThreshold) {
                $offersToInsert[$offerId] = [
                    'server_id' => $serverId,
                    'offer_id' => $offerId,
                    'player_id' => (int) ($raw['senderID'] ?? 0),
                    'sender_name' => (string) ($raw['senderName'] ?? 'Unknown'),
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
                    'collected_at' => $collectedAt->toDateTimeString(),
                ];
            }

            $historyToInsert[$offerId] = [
                'server_id' => $serverId,
                'offer_id' => $offerId,
                'player_id' => (int) ($raw['senderID'] ?? 0),
                'item_id' => $itemId,
                'item_name' => $itemName,
                'amount' => $amount,
                'target_item_id' => $targetItemId,
                'target_item_name' => $targetItemName,
                'target_amount' => $targetAmount,
                'price' => $price,
                'volume' => $volume,
                'collected_at' => $collectedAt->toDateTimeString(),
            ];
        }

        return [
            'offers' => array_values($offersToInsert),
            'history' => array_values($historyToInsert),
        ];
    }
}
