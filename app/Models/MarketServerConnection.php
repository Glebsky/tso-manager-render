<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketServerConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'locale',
        'display_name',
        'account_id',
        'verification_status',
        'sync_status',
        'last_synced_at',
        'last_error',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
