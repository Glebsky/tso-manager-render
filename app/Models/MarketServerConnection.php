<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketServerConnection extends Model
{
    protected $fillable = [
        'server_id',
        'locale',
        'display_name',
        'account_id',
        'verification_status',
        'sync_status',
        'last_synced_at',
        'last_error',
        'data_version',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'data_version' => 'integer',
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
