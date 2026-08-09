<?php

declare(strict_types=1);

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
        'password' => 'encrypted',
        'dso_auth_user' => 'encrypted',
        'dso_auth_token' => 'encrypted',
        'last_sync_at' => 'datetime',
    ];

    protected $appends = [
        'server_name',
        'is_market_connected',
        'avatar_id',
        'building_count',
    ];

    protected $hidden = [
        'password',
        'zone_data',
    ];

    public function getAvatarIdAttribute(): ?int
    {
        if (empty($this->zone_data)) {
            return null;
        }

        try {
            $data = is_array($this->zone_data) ? $this->zone_data : json_decode($this->zone_data, true);

            return isset($data['avatarId']) ? (int) $data['avatarId'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getBuildingCountAttribute(): ?int
    {
        if (empty($this->zone_data)) {
            return null;
        }

        try {
            $data = is_array($this->zone_data) ? $this->zone_data : json_decode($this->zone_data, true);

            return isset($data['buildings']) && is_array($data['buildings']) ? count($data['buildings']) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getIsMarketConnectedAttribute(): bool
    {
        return MarketServerConnection::where('account_id', $this->id)->exists();
    }

    public function getServerNameAttribute(): ?string
    {
        if (empty($this->zone_data)) {
            return null;
        }

        try {
            $data = is_array($this->zone_data) ? $this->zone_data : json_decode($this->zone_data, true);

            return $data['gameWorldName'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
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
