<?php

declare(strict_types=1);

namespace App\Services\Market;

use App\Enums\MarketItemKind;
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
final readonly class MarketOfferQueryService
{
    public function __construct(private int $offerLifetimeHours) {}

    public function activeSince(?CarbonInterface $now = null): CarbonInterface
    {
        return ($now !== null ? $now->copy() : Carbon::now())
            ->subHours($this->offerLifetimeHours);
    }

    public function expiresAt(CarbonInterface $createdAt): CarbonInterface
    {
        return $createdAt->copy()->addHours($this->offerLifetimeHours);
    }

    /**
     * @return Collection<int, MarketOffer>
     */
    public function activeOffers(string $serverId, ?MarketItemKind $kind = null): Collection
    {
        $since = $this->activeSince();

        $kindValue = $kind?->value;

        return MarketOffer::query()
            ->where('server_id', $serverId)
            ->when($kindValue !== null, fn ($query) => $query->where('item_kind', $kindValue))
            ->where('created_at', '>', $since)
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
            ->where('server_id', $serverId)
            ->where('item_id', $itemId)
            ->where('target_item_id', $targetItemId)
            ->where('created_at', '>', $since)
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
     * @return SupportCollection<int, MarketOffer>
     */
    public function activeInfoByPair(string $serverId): SupportCollection
    {
        $since = $this->activeSince();

        return MarketOffer::query()
            ->where('server_id', $serverId)
            ->where('created_at', '>', $since)
            ->selectRaw('item_id, target_item_id, sum(volume) as volume, count(*) as offers_count, count(distinct player_id) as sellers_count')
            ->groupBy('item_id', 'target_item_id')
            ->get()
            ->toBase();
    }
}
