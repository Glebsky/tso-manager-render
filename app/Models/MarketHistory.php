<?php

namespace App\Models;

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
        'item_id',
        'item_name',
        'amount',
        'target_item_id',
        'target_item_name',
        'target_amount',
        'price',
        'volume',
        'collected_at',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
        'price' => 'double',
    ];

    public $timestamps = false;
}
