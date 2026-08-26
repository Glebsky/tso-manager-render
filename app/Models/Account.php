<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\SafeEncrypted;
use App\Casts\ZoneDataCast;
use App\Support\Zone\ZoneSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    private ?ZoneSnapshot $snapshotInstance = null;

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
        'password' => SafeEncrypted::class,
        'dso_auth_user' => SafeEncrypted::class,
        'dso_auth_token' => SafeEncrypted::class,
        'last_sync_at' => 'datetime',
        'zone_data' => ZoneDataCast::class,
    ];

    protected $appends = [
        'server_name',
        'avatar_id',
        'building_count',
    ];

    protected $hidden = [
        'password',
        'zone_data',
    ];

    public function snapshot(): ZoneSnapshot
    {
        return $this->snapshotInstance ??= ZoneSnapshot::fromData($this->zone_data);
    }

    public function getAvatarIdAttribute(): ?int
    {
        return $this->snapshot()->avatarId();
    }

    public function getBuildingCountAttribute(): ?int
    {
        return $this->snapshot()->buildingCount();
    }

    public function getServerNameAttribute(): ?string
    {
        return $this->snapshot()->serverName();
    }

    public function getIsMarketConnectedAttribute(): bool
    {
        if (array_key_exists('market_server_connections_exists', $this->attributes)) {
            return (bool) $this->attributes['market_server_connections_exists'];
        }

        if ($this->relationLoaded('marketServerConnections')) {
            return $this->marketServerConnections->isNotEmpty();
        }

        return $this->marketServerConnections()->exists();
    }

    /**
     * @return HasMany<MarketServerConnection, $this>
     */
    public function marketServerConnections(): HasMany
    {
        return $this->hasMany(MarketServerConnection::class);
    }

    /**
     * @return HasMany<ScheduledTask, $this>
     */
    public function scheduledTasks(): HasMany
    {
        return $this->hasMany(ScheduledTask::class);
    }

    /**
     * @return HasMany<BotLog, $this>
     */
    public function botLogs(): HasMany
    {
        return $this->hasMany(BotLog::class);
    }
}
