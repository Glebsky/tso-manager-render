<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScheduleType;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property int $account_id
 * @property TaskType $task_type
 * @property ScheduleType $schedule_type
 * @property ?string $run_at_time
 * @property ?Carbon $run_at_datetime
 * @property ?int $interval_hours
 * @property ?int $interval_minutes
 * @property ?array<string, mixed> $payload
 * @property bool $is_active
 * @property TaskStatus $status
 * @property ?Carbon $queued_at
 * @property ?string $execution_token
 * @property int $completed_steps
 * @property ?Carbon $last_run_at
 * @property ?string $last_result
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Account $account
 */
class ScheduledTask extends Model
{
    protected $attributes = [
        'status' => 'pending',
        'is_active' => true,
    ];

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
        'status' => TaskStatus::class,
        'task_type' => TaskType::class,
        'schedule_type' => ScheduleType::class,
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
