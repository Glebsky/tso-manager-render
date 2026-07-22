<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketOffer extends Model
{
    protected $fillable = [
        'server_id',
        'offer_id',
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

    protected $casts = [
        'created_at' => 'datetime',
        'collected_at' => 'datetime',
        'price' => 'double',
    ];

    public $timestamps = false;
}
