<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MarketItemKind;
use Illuminate\Database\Eloquent\Model;

/**
 * @property ?int $offers_count
 * @property ?int $sellers_count
 */
class MarketOffer extends Model
{
    protected $fillable = [
        'server_id',
        'offer_id',
        'player_id',
        'sender_name',
        'item_kind',
        'item_id',
        'item_name',
        'item_subject',
        'amount',
        'target_item_kind',
        'target_item_id',
        'target_item_name',
        'target_item_subject',
        'target_amount',
        'price',
        'volume',
        'lots_remaining',
        'trade_type',
        'slot_type',
        'total_lots',
        'created_at',
        'collected_at',
    ];

    protected $casts = [
        'item_kind' => MarketItemKind::class,
        'target_item_kind' => MarketItemKind::class,
        'created_at' => 'datetime',
        'collected_at' => 'datetime',
        'price' => 'double',
        'trade_type' => 'integer',
        'slot_type' => 'integer',
        'total_lots' => 'integer',
    ];

    public $timestamps = false;
}
