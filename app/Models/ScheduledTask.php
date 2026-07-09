<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledTask extends Model
{
    protected $fillable = [
        'account_id',
        'task_type',
        'payload',
        'run_at_time',
        'is_active',
        'last_run_at',
        'last_result',
    ];

    protected $casts = [
        'payload'     => 'array',
        'is_active'   => 'boolean',
        'last_run_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
