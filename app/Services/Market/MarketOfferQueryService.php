<?php

declare(strict_types=1);

namespace App\Services\Market;

use App\Models\MarketOffer;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Owns every read query about *active* market offers.
 *
 * The `where('created_at', '>=', now()->subHours(6))` literal used to appear
 * in five places; the offer lifetime is now configuration injected once.
 */
final class MarketOfferQueryService
{
    public function __construct(private readonly int $offerLifetimeHours) {}

    public function activeSince(?CarbonInterface $now = null): CarbonInterface
    {
        $reference = $now instanceof CarbonInterface ? $now->copy() : Carbon::now();

        return $reference->subHours($this->offerLifetimeHours);
    }

    public function expiresAt(CarbonInterface $createdAt): CarbonInterface
    {
        return $createdAt->copy()->addHours($this->offerLifetimeHours);
    }

    /**
     * @return Collection<int, MarketOffer>
     */
    public function activeOffers(string $serverId): Collection
    {
        $since = $this->activeSince();

        return MarketOffer::query()
            ->tap(fn ($q) => $this->applyServerFilter($q, $serverId))
            ->where(static function ($query) use ($since): void {
                $query->where('created_at', '>=', $since)
                    ->orWhere('collected_at', '>=', $since);
            })
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return Collection<int, MarketOffer>
     */
    public function activeOffersForPair(string $serverId, string $itemId, string $targetItemId): Collection
    {
        $since = $this->activeSince();

        return MarketOffer::query()
            ->tap(fn ($q) => $this->applyServerFilter($q, $serverId))
            ->where('item_id', $itemId)
            ->where('target_item_id', $targetItemId)
            ->where(static function ($query) use ($since): void {
                $query->where('created_at', '>=', $since)
                    ->orWhere('collected_at', '>=', $since);
            })
            ->get();
    }

    /**
     * @param  Collection<int, MarketOffer>  $offers
     * @return array{volume: int, offers_count: int, sellers_count: int}
     */
    public function summarize(Collection $offers): array
    {
        return [
            'volume' => (int) $offers->sum('volume'),
            'offers_count' => $offers->count(),
            'sellers_count' => $offers->pluck('player_id')->unique()->count(),
        ];
    }

    /**
     * Per-pair aggregates of the currently active offers.
     *
     * @return SupportCollection<int, object>
     */
    public function activeInfoByPair(string $serverId): SupportCollection
    {
        $since = $this->activeSince();

        return MarketOffer::query()
            ->tap(fn ($q) => $this->applyServerFilter($q, $serverId))
            ->where(static function ($query) use ($since): void {
                $query->where('created_at', '>=', $since)
                    ->orWhere('collected_at', '>=', $since);
            })
            ->selectRaw('item_id, target_item_id, sum(volume) as volume, count(*) as offers_count, count(distinct player_id) as sellers_count')
            ->groupBy('item_id', 'target_item_id')
            ->get()
            ->toBase();
    }

    private function applyServerFilter(object $query, string $serverId): void
    {
        $region = explode('_', $serverId)[0];
        $query->where(static function ($q) use ($serverId, $region): void {
            $q->where('server_id', $serverId)
                ->orWhere('server_id', $region)
                ->orWhere('server_id', 'LIKE', "{$region}\\_%");
        });
    }
}
