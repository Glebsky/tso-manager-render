<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MarketItemKind;
use Illuminate\Database\Eloquent\Model;

/**
 * @property mixed $time_bucket
 * @property ?int $offers_count
 * @property ?int $sellers_count
 * @property ?int $total_volume
 * @property ?float $avg_amount
 * @property ?float $avg_target_amount
 */
class MarketHistory extends Model
{
    protected $table = 'market_history';

    protected $fillable = [
        'server_id',
        'offer_id',
        'player_id',
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
        'trade_type',
        'slot_type',
        'total_lots',
        'collected_at',
    ];

    protected $casts = [
        'item_kind' => MarketItemKind::class,
        'target_item_kind' => MarketItemKind::class,
        'collected_at' => 'datetime',
        'price' => 'double',
        'trade_type' => 'integer',
        'slot_type' => 'integer',
        'total_lots' => 'integer',
    ];

    public $timestamps = false;
}
