<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $account_id
 * @property string $name
 * @property int $sort_order
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Account $account
 */
class TaskGroup extends Model
{
    protected $attributes = [
        'sort_order' => 0,
    ];

    protected $fillable = [
        'account_id',
        'name',
        'sort_order',
    ];

    protected $casts = [
        'account_id' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return HasMany<ScheduledTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ScheduledTask::class, 'group_name', 'name')
            ->where('scheduled_tasks.account_id', $this->account_id);
    }
}
