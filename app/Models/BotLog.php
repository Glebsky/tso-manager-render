<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'level',
        'message',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Boot: auto-set created_at on create.
     */
    protected static function booted(): void
    {
        static::creating(function (BotLog $log) {
            if (is_null($log->created_at)) {
                $log->created_at = now();
            }
        });
    }
}
