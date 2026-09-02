<?php

declare(strict_types=1);

namespace App\Services\Market\Sync;

use App\Services\Market\Tradeables\CompositeTradeableNameResolver;
use App\Services\Market\Tradeables\TradeableIdFactory;
use App\Services\Market\Tradeables\TradeOfferDecoder;
use Carbon\CarbonInterface;

/**
 * Service responsible for parsing raw market offer strings into structured data.
 */
readonly class MarketOfferParser
{
    public function __construct(
        private TradeOfferDecoder $decoder,
        private TradeableIdFactory $idFactory,
        private CompositeTradeableNameResolver $nameResolver,
        private int $offerLifetimeHours = 6,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rawOffers
     * @return array{offers: list<array<string, mixed>>, history: list<array<string, mixed>>, unparsed_offers: int}
     */
    public function parse(array $rawOffers, string $serverId, CarbonInterface $collectedAt): array
    {
        $offersToInsert = [];
        $historyToInsert = [];
        $unparsedOffers = 0;

        $expirationThreshold = (int) $collectedAt->timestamp - ($this->offerLifetimeHours * 3600);

        foreach ($rawOffers as $raw) {
            $offerStr = (string) ($raw['offer'] ?? '');
            if ($offerStr === '') {
                $unparsedOffers++;

                continue;
            }

            $offerId = (int) ($raw['id'] ?? 0);
            if ($offerId <= 0) {
                $unparsedOffers++;

                continue;
            }

            $tradeType = is_numeric($raw['type'] ?? null) ? (int) $raw['type'] : -1;
            $decoded = $this->decoder->decode($offerStr, $tradeType);

            if ($decoded === null) {
                $unparsedOffers++;

                continue;
            }

            $offerUnits = $decoded->offer->units;
            $costUnits = $decoded->costs?->units;
            $price = $costUnits === null ? null : (float) $costUnits / (float) $offerUnits;
            $lotsRemaining = (int) ($raw['lotsRemaining'] ?? 1);
            $volume = $offerUnits * $lotsRemaining;
            $amount = $decoded->offer->rawAmount ?? $offerUnits;
            $targetAmount = $decoded->costs !== null ? ($decoded->costs->rawAmount ?? $decoded->costs->units) : null;

            $itemId = $this->idFactory->fromSide($decoded->offer);
            $itemName = $this->nameResolver->resolveTradeSide($decoded->offer, $itemId);

            $targetItemId = $decoded->costs !== null ? $this->idFactory->fromSide($decoded->costs) : null;
            $targetItemName = $decoded->costs !== null ? $this->nameResolver->resolveTradeSide($decoded->costs, $targetItemId) : null;

            $gameCreatedMs = is_numeric($raw['created'] ?? null) ? (int) $raw['created'] : 0;
            $gameCreatedSec = $gameCreatedMs > 0 ? (int) ($gameCreatedMs / 1000) : (int) $collectedAt->timestamp;
            $gameCreatedAt = date('Y-m-d H:i:s', $gameCreatedSec);

            $slotType = is_numeric($raw['slotType'] ?? null) ? (int) $raw['slotType'] : null;

            // Only add to active offers if not already expired at collection time
            if ($gameCreatedSec > $expirationThreshold) {
                $offersToInsert[$offerId] = [
                    'server_id' => $serverId,
                    'offer_id' => $offerId,
                    'player_id' => (int) ($raw['senderID'] ?? 0),
                    'sender_name' => (string) ($raw['senderName'] ?? 'Unknown'),
                    'item_kind' => $decoded->offer->kind->value,
                    'item_id' => $itemId,
                    'item_name' => $itemName,
                    'item_subject' => $decoded->offer->subject,
                    'amount' => $amount,
                    'target_item_kind' => $decoded->costs?->kind->value,
                    'target_item_id' => $targetItemId,
                    'target_item_name' => $targetItemName,
                    'target_item_subject' => $decoded->costs?->subject,
                    'target_amount' => $targetAmount,
                    'price' => $price,
                    'volume' => $volume,
                    'lots_remaining' => $lotsRemaining,
                    'trade_type' => $decoded->tradeType,
                    'slot_type' => $slotType,
                    'total_lots' => $decoded->totalLots,
                    'created_at' => $gameCreatedAt,
                    'collected_at' => $collectedAt->toDateTimeString(),
                ];
            }

            $historyToInsert[$offerId] = [
                'server_id' => $serverId,
                'offer_id' => $offerId,
                'player_id' => (int) ($raw['senderID'] ?? 0),
                'item_kind' => $decoded->offer->kind->value,
                'item_id' => $itemId,
                'item_name' => $itemName,
                'item_subject' => $decoded->offer->subject,
                'amount' => $amount,
                'target_item_kind' => $decoded->costs?->kind->value,
                'target_item_id' => $targetItemId,
                'target_item_name' => $targetItemName,
                'target_item_subject' => $decoded->costs?->subject,
                'target_amount' => $targetAmount,
                'price' => $price,
                'volume' => $volume,
                'trade_type' => $decoded->tradeType,
                'slot_type' => $slotType,
                'total_lots' => $decoded->totalLots,
                'collected_at' => $collectedAt->toDateTimeString(),
            ];
        }

        return [
            'offers' => array_values($offersToInsert),
            'history' => array_values($historyToInsert),
            'unparsed_offers' => $unparsedOffers,
        ];
    }
}
