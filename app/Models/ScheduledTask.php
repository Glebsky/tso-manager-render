<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledTask extends Model
{
    protected $fillable = [
        'name',
        'account_id',
        'task_type',
        'payload',
        'run_at_time',
        'is_active',
        'last_run_at',
        'last_result',
        'schedule_type',
        'run_at_datetime',
        'interval_hours',
        'interval_minutes',
        'status',
        'queued_at',
        'execution_token',
        'completed_steps',
    ];

    protected $casts = [
        'payload' => 'array',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'run_at_datetime' => 'datetime',
        'queued_at' => 'datetime',
        'interval_hours' => 'integer',
        'interval_minutes' => 'integer',
        'completed_steps' => 'integer',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
