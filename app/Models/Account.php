<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\SafeEncrypted;
use App\Casts\ZoneDataCast;
use App\Support\Zone\ZoneSnapshot;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $username
 * @property string $password
 * @property string $region
 * @property string $nickname
 * @property ?string $dso_auth_user
 * @property ?string $dso_auth_token
 * @property ?string $bb_url
 * @property string $status
 * @property ?array<string, mixed> $zone_data
 * @property ?Carbon $last_sync_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?string $server_name
 * @property ?int $avatar_id
 * @property ?int $building_count
 * @property bool $is_market_connected
 * @property Collection<int, MarketServerConnection> $marketServerConnections
 * @property Collection<int, ScheduledTask> $scheduledTasks
 * @property Collection<int, BotLog> $botLogs
 */
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

    /**
     * Scope a query to only select lightweight columns excluding heavy zone_data.
     *
     * @param  Builder<Account>  $query
     * @return Builder<Account>
     */
    public function scopeLite(Builder $query): Builder
    {
        return $query->select([
            'id',
            'username',
            'nickname',
            'region',
            'status',
            'dso_auth_user',
            'bb_url',
            'last_sync_at',
            'created_at',
            'updated_at',
        ]);
    }

    public function snapshot(): ZoneSnapshot
    {
        if ($this->snapshotInstance !== null) {
            return $this->snapshotInstance;
        }

        if (! array_key_exists('zone_data', $this->attributes) || $this->attributes['zone_data'] === null) {
            return $this->snapshotInstance = ZoneSnapshot::fromData([]);
        }

        return $this->snapshotInstance = ZoneSnapshot::fromData($this->zone_data);
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
