<?php

declare(strict_types=1);

namespace App\Services\Market\Sync;

use App\Models\MarketHistory;
use App\Models\MarketOffer;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Service responsible for batch database persistence of active offers and history.
 */
class MarketOfferPersister
{
    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  list<array<string, mixed>>  $history
     *
     * @throws Throwable
     */
    public function persist(string $serverId, array $offers, array $history): void
    {
        DB::transaction(static function () use ($serverId, $offers, $history) {
            if (! empty($offers)) {
                $updateColumns = [
                    'player_id',
                    'sender_name',
                    'item_id',
                    'item_name',
                    'amount',
                    'target_item_id',
                    'target_item_name',
                    'target_amount',
                    'price',
                    'volume',
                    'lots_remaining',
                    'created_at',
                    'collected_at',
                ];

                $dedupedOffers = [];
                foreach ($offers as $offer) {
                    $key = (string) ($offer['server_id'] ?? $serverId).':'.(string) ($offer['offer_id'] ?? '');
                    $dedupedOffers[$key] = $offer;
                }

                foreach (array_chunk(array_values($dedupedOffers), 200) as $chunk) {
                    MarketOffer::upsert($chunk, ['server_id', 'offer_id'], $updateColumns);
                }

                $collectedAt = $offers[0]['collected_at'] ?? null;
                if ($collectedAt !== null) {
                    MarketOffer::where('server_id', $serverId)
                        ->where(static function ($query) use ($collectedAt) {
                            $query->where('collected_at', '<', $collectedAt)
                                ->orWhereNull('collected_at');
                        })
                        ->delete();
                }
            } else {
                MarketOffer::where('server_id', $serverId)->delete();
            }

            if (! empty($history)) {
                $offerIds = array_column($history, 'offer_id');
                $existingIds = MarketHistory::where('server_id', $serverId)
                    ->whereIn('offer_id', $offerIds)
                    ->pluck('offer_id')
                    ->all();

                $existingIdsSet = array_flip($existingIds);
                $filteredHistory = array_values(array_filter(
                    $history,
                    static fn (array $h): bool => ! isset($existingIdsSet[$h['offer_id']])
                ));

                if (! empty($filteredHistory)) {
                    foreach (array_chunk($filteredHistory, 200) as $chunk) {
                        MarketHistory::insert($chunk);
                    }
                }
            }
        });
    }
}
