<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = [
        'username',
        'password',
        'region',
        'nickname',
        'dso_auth_user',
        'dso_auth_token',
        'bb_url',
        'status',
        'zone_data',
        'last_sync_at',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
    ];

    public function getPasswordAttribute($value)
    {
        if (empty($value)) {
            return '';
        }
        try {
            return decrypt($value);
        } catch (\Throwable $e) {
            try {
                return decrypt($value, false);
            } catch (\Throwable $ex) {
                return '';
            }
        }
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = encrypt($value);
    }

    public function scheduledTasks(): HasMany
    {
        return $this->hasMany(ScheduledTask::class);
    }

    public function botLogs(): HasMany
    {
        return $this->hasMany(BotLog::class);
    }
}
