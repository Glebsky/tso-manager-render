<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\MarketItemKind;
use App\Models\MarketOffer;
use App\Services\Market\Contracts\ResourceNameResolver;
use App\Services\Market\MarketOfferQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * Single serializer for a market offer.
 *
 * @mixin MarketOffer
 */
class MarketOfferResource extends JsonResource
{
    private ?ResourceNameResolver $names = null;

    private ?MarketOfferQueryService $offers = null;

    /**
     * Inject the collaborators the serializer needs. Explicit setter injection
     * is used because Laravel resources own their constructor signature.
     */
    public function using(ResourceNameResolver $names, MarketOfferQueryService $offers): self
    {
        $this->names = $names;
        $this->offers = $offers;

        return $this;
    }

    /**
     * Serialize a set of offers to plain arrays (used for cached payloads).
     *
     * @param  iterable<MarketOffer>  $offers
     * @return list<array<string, mixed>>
     */
    public static function listFrom(iterable $offers, ResourceNameResolver $names, MarketOfferQueryService $queries): array
    {
        $serialized = [];

        foreach ($offers as $offer) {
            $serialized[] = self::make($offer)->using($names, $queries)->toArray(request());
        }

        return $serialized;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $names = $this->names ?? throw new LogicException('MarketOfferResource requires using() to be called first.');
        $offers = $this->offers ?? throw new LogicException('MarketOfferResource requires using() to be called first.');

        /** @var mixed $rawItemKind */
        $rawItemKind = $this->resource->item_kind ?? null;
        $itemKind = $rawItemKind instanceof MarketItemKind
            ? $rawItemKind->value
            : (is_string($rawItemKind) ? $rawItemKind : 'resource');

        /** @var mixed $rawTargetItemKind */
        $rawTargetItemKind = $this->resource->target_item_kind ?? null;
        $targetItemKind = $rawTargetItemKind instanceof MarketItemKind
            ? $rawTargetItemKind->value
            : (is_string($rawTargetItemKind) ? $rawTargetItemKind : null);

        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'offer_id' => $this->offer_id,
            'sender_name' => $this->sender_name,
            'item_kind' => $itemKind,
            'item_id' => $this->item_id,
            'item_name' => $names->resolve($this->item_id, $this->item_name),
            'item_subject' => $this->item_subject,
            'amount' => $this->amount,
            'target_item_kind' => $targetItemKind,
            'target_item_id' => $this->target_item_id,
            'target_item_name' => $this->target_item_id !== null ? $names->resolve($this->target_item_id, $this->target_item_name) : null,
            'target_item_subject' => $this->target_item_subject,
            'target_amount' => $this->target_amount,
            'price' => $this->price !== null ? round((float) $this->price, 4) : null,
            'volume' => $this->volume,
            'lots_remaining' => $this->lots_remaining,
            'created_at' => $this->created_at->toIso8601String(),
            'expires_at' => $offers->expiresAt($this->created_at)->toIso8601String(),
        ];
    }
}
