<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LogLevel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $account_id
 * @property LogLevel $level
 * @property string $message
 * @property ?Carbon $created_at
 * @property ?Account $account
 */
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
        'level' => LogLevel::class,
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Boot: auto-set created_at on create.
     */
    protected static function booted(): void
    {
        static::creating(static function (BotLog $log) {
            if (is_null($log->created_at)) {
                $log->created_at = now();
            }
        });
    }
}
