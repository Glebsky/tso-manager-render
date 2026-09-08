<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\SafeEncrypted;
use App\Casts\ZoneDataCast;
use App\Services\MarketServerVerificationService;
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
 * @property ?string $detected_server_id
 * @property ?string $detected_locale
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
        'game_world_name',
        'avatar_id',
        'building_count',
        'zone_data',
        'last_sync_at',
    ];

    protected $casts = [
        'password' => SafeEncrypted::class,
        'dso_auth_user' => SafeEncrypted::class,
        'dso_auth_token' => SafeEncrypted::class,
        'last_sync_at' => 'datetime',
        'avatar_id' => 'integer',
        'building_count' => 'integer',
        'zone_data' => ZoneDataCast::class,
    ];

    protected $appends = [
        'server_name',
        'detected_server_id',
        'detected_locale',
        'avatar_id',
        'building_count',
    ];

    protected $hidden = [
        'password',
        'zone_data',
    ];

    protected static function booted(): void
    {
        static::saving(static function (Account $account): void {
            if ($account->isDirty('zone_data')) {
                $account->snapshotInstance = null;
                $snapshot = ZoneSnapshot::fromData($account->attributes['zone_data'] ?? null);
                $account->avatar_id = $snapshot->avatarId();
                $account->building_count = $snapshot->buildingCount();
                $account->game_world_name = $snapshot->serverName();
            }
        });
    }

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
            'game_world_name',
            'avatar_id',
            'building_count',
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
        if (array_key_exists('avatar_id', $this->attributes) && $this->attributes['avatar_id'] !== null) {
            return (int) $this->attributes['avatar_id'];
        }

        return $this->snapshot()->avatarId();
    }

    public function getBuildingCountAttribute(): ?int
    {
        if (array_key_exists('building_count', $this->attributes) && $this->attributes['building_count'] !== null) {
            return (int) $this->attributes['building_count'];
        }

        return $this->snapshot()->buildingCount();
    }

    public function getServerNameAttribute(): ?string
    {
        if (array_key_exists('game_world_name', $this->attributes) && $this->attributes['game_world_name'] !== null) {
            return (string) $this->attributes['game_world_name'];
        }

        return $this->snapshot()->serverName();
    }

    /**
     * @var array{detected_server_id: ?string, detected_locale: ?string, game_world: ?string, confidence: string}|null
     */
    private ?array $detectedServerCache = null;

    /**
     * @return array{detected_server_id: ?string, detected_locale: ?string, game_world: ?string, confidence: string}
     */
    public function detectedServerInfo(): array
    {
        if ($this->detectedServerCache !== null) {
            return $this->detectedServerCache;
        }

        return $this->detectedServerCache = app(MarketServerVerificationService::class)->detectServerForAccount($this);
    }

    public function getDetectedServerIdAttribute(): ?string
    {
        return $this->detectedServerInfo()['detected_server_id'];
    }

    public function getDetectedLocaleAttribute(): ?string
    {
        return $this->detectedServerInfo()['detected_locale'];
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
