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
            // Clear active offers for this server ONLY
            MarketOffer::where('server_id', $serverId)->delete();

            // Chunk inserts to avoid database limits
            foreach (array_chunk($offers, 200) as $chunk) {
                MarketOffer::insert($chunk);
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
