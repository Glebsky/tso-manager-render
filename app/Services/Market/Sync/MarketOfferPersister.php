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

                foreach (array_chunk($offers, 200) as $chunk) {
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

            // Filter out history entries that already exist for this server
            $offerIds = array_column($history, 'offer_id');
            $existingIds = [];
            if (! empty($offerIds)) {
                $existingIds = collect($offerIds)
                    ->chunk(500)
                    ->flatMap(function ($idChunk) use ($serverId) {
                        return MarketHistory::where('server_id', $serverId)
                            ->whereIn('offer_id', $idChunk)
                            ->pluck('offer_id');
                    })
                    ->all();
            }

            $existingIdsSet = array_flip($existingIds);
            $filteredHistory = [];
            foreach ($history as $h) {
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
    }
}
