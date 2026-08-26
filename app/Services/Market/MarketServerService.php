<?php

declare(strict_types=1);

namespace App\Services\Market;

use App\Exceptions\MarketOperationException;
use App\Models\Account;
use App\Models\MarketServerConnection;
use App\Models\MarketSyncLog;
use App\Services\MarketCacheService;
use App\Services\MarketServerVerificationService;
use App\Services\MarketSyncService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Every use case around market server connections.
 *
 * Previously all of this lived directly in MarketAnalyticsController, mixed
 * with analytics, caching and serialization concerns. Extracting it gives the
 * controller a single responsibility (translate HTTP to a use case) and makes
 * the rules reusable from console commands and jobs.
 */
final class MarketServerService
{
    private const string ACCOUNT_COLUMNS = 'account:id,username,nickname,region,status';

    private const string ACCOUNT_COLUMNS_WITH_ZONE = 'account:id,username,nickname,region,status,zone_data';

    public function __construct(
        private readonly MarketCacheService $cache,
        private readonly MarketServerVerificationService $verification,
        private readonly MarketSyncService $sync,
        private readonly MarketSettingsService $settings,
        private readonly ServerPresetProvider $presets,
    ) {}

    /**
     * Payload backing both GET /market/servers and GET /market/settings.
     *
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $servers = $this->normalizedServers();

        $accounts = Account::select('id', 'username', 'nickname', 'region', 'status', 'zone_data')
            ->latest()
            ->get();

        $firstAccountId = MarketServerConnection::whereNotNull('account_id')->value('account_id');
        $lastSyncTime = MarketSyncLog::where('status', 'SUCCESS')->latest()->value('created_at');

        return [
            'servers' => $servers,
            'accounts' => $accounts,
            'presets' => $this->presets->all(),
            'settings' => [
                'account_id' => $firstAccountId ? (int) $firstAccountId : null,
                'sync_interval' => $this->settings->syncInterval(),
                'custom_interval_minutes' => $this->settings->customIntervalMinutes(),
                'cache_strategy' => (string) config('market.cache_strategy', 'bulk'),
            ],
            'connection_status' => $servers->contains(fn ($server): bool => $server->sync_status === 'connected') ? 'Connected' : 'Disconnected',
            'last_sync' => $lastSyncTime ? $lastSyncTime->toIso8601String() : 'Never',
        ];
    }

    /**
     * Servers visible to the public portal.
     *
     * @return Collection<int, MarketServerConnection>
     */
    public function publicServers(): Collection
    {
        return MarketServerConnection::whereNotNull('account_id')
            ->whereHas('account')
            ->with(self::ACCOUNT_COLUMNS_WITH_ZONE)
            ->select('id', 'server_id', 'locale', 'display_name', 'sync_status', 'account_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * @throws MarketOperationException
     */
    public function createForAccount(int $accountId): MarketServerConnection
    {
        $account = Account::findOrFail($accountId);
        $detection = $this->verification->detectServerForAccount($account);

        if (empty($detection['detected_server_id'])) {
            throw MarketOperationException::unprocessable(
                __('ui.market.api.account_no_region', ['username' => $account->username])
            );
        }

        $serverId = strtolower((string) $detection['detected_server_id']);
        $locale = strtoupper((string) ($detection['detected_locale'] ?? $serverId));

        if (MarketServerConnection::where('server_id', $serverId)->exists()) {
            $worldOrServer = $account->server_name ?: strtoupper($serverId);

            throw MarketOperationException::unprocessable(
                __('ui.market.api.server_exists', ['server' => $worldOrServer])
            );
        }

        $server = MarketServerConnection::create([
            'server_id' => $serverId,
            'locale' => $locale,
            'display_name' => $this->displayName($account->server_name, $serverId),
            'account_id' => $account->id,
            'verification_status' => 'verified',
            'sync_status' => 'connected',
        ]);

        $this->invalidate($serverId);

        return $server->load(self::ACCOUNT_COLUMNS);
    }

    public function createdMessage(MarketServerConnection $server): string
    {
        return __('ui.market.api.server_created', [
            'server' => $server->server_id,
            'username' => $server->account?->username,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws MarketOperationException
     */
    public function update(MarketServerConnection $server, array $attributes): MarketServerConnection
    {
        if (array_key_exists('account_id', $attributes)) {
            $server->account_id = $attributes['account_id'];
        }

        if (isset($attributes['sync_status'])) {
            $server->sync_status = $attributes['sync_status'];
        }

        if (! empty($server->account_id)) {
            $this->applyAccountVerification($server);
        } else {
            $server->verification_status = 'unverified';
            $server->sync_status = 'not_configured';
        }

        $server->save();

        $this->invalidate($server->server_id);

        return $server->load(self::ACCOUNT_COLUMNS);
    }

    public function delete(MarketServerConnection $server): void
    {
        $serverId = $server->server_id;
        $server->delete();

        $this->invalidate($serverId);
    }

    /**
     * Verify that the assigned account really belongs to this server.
     *
     * @return array{payload: array<string, mixed>, status: int}
     */
    public function verify(MarketServerConnection $server): array
    {
        if (empty($server->account_id)) {
            $server->update([
                'verification_status' => 'unverified',
                'last_error' => __('ui.market.api.no_account_assigned'),
            ]);

            return [
                'payload' => [
                    'success' => false,
                    'status' => 'unverified',
                    'message' => __('ui.market.api.no_account_assigned'),
                    'server' => $server->load(self::ACCOUNT_COLUMNS),
                ],
                'status' => 200,
            ];
        }

        $account = Account::find($server->account_id);

        if (! $account) {
            $server->update([
                'verification_status' => 'error',
                'last_error' => __('ui.market.api.account_not_found'),
            ]);

            return [
                'payload' => [
                    'success' => false,
                    'status' => 'error',
                    'message' => __('ui.market.api.account_not_found'),
                    'server' => $server->load(self::ACCOUNT_COLUMNS),
                ],
                'status' => 422,
            ];
        }

        $result = $this->verification->verifyAccountServerMatch($account, $server);
        $server->verification_status = $result['status'];

        if ($result['status'] === 'mismatch') {
            $server->last_error = $result['message'];
        } elseif ($result['status'] === 'verified' && $server->last_error && str_contains($server->last_error, 'mismatch')) {
            $server->last_error = null;
        }

        $server->save();

        return [
            'payload' => [
                'success' => $result['status'] === 'verified',
                'status' => $result['status'],
                'message' => $result['message'],
                'detected_server' => $result['detected_server'] ?? null,
                'server' => $server->load(self::ACCOUNT_COLUMNS),
            ],
            'status' => 200,
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws MarketOperationException
     */
    public function syncNow(MarketServerConnection $server): array
    {
        if (empty($server->account_id)) {
            throw MarketOperationException::unprocessable(__('ui.market.api.assign_account_first'));
        }

        $account = Account::find($server->account_id);

        if (! $account) {
            throw MarketOperationException::unprocessable(__('ui.market.api.account_not_found'));
        }

        try {
            return $this->sync->sync($account, $server->server_id);
        } catch (Throwable $e) {
            throw MarketOperationException::serverError(
                __('ui.market.api.sync_failed', ['error' => $e->getMessage()])
            );
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws MarketOperationException
     */
    public function syncServerId(string $serverId): array
    {
        $server = MarketServerConnection::where('server_id', $serverId)->first();

        if (! $server || empty($server->account_id)) {
            throw MarketOperationException::unprocessable("No account configured for server [{$serverId}].");
        }

        return $this->syncNow($server);
    }

    /**
     * Renames legacy server ids/display names to the game world they belong to.
     *
     * @return Collection<int, MarketServerConnection>
     */
    private function normalizedServers(): Collection
    {
        return MarketServerConnection::with(self::ACCOUNT_COLUMNS_WITH_ZONE)
            ->orderBy('id')
            ->get()
            ->map(function (MarketServerConnection $server): MarketServerConnection {
                if ($server->account && $server->account->server_name) {
                    $worldSlug = Str::slug($server->account->server_name, '_');

                    if (! empty($worldSlug)) {
                        $worldServerId = strtolower((string) $server->account->region).'_'.$worldSlug;

                        if ($server->server_id !== $worldServerId
                            && ! MarketServerConnection::where('server_id', $worldServerId)->where('id', '!=', $server->id)->exists()
                        ) {
                            $server->server_id = $worldServerId;
                            $server->save();
                        }
                    }

                    $server->display_name = $this->displayName($server->account->server_name, $server->server_id);
                } elseif (str_contains((string) $server->display_name, 'Market (The Settlers')
                    || str_contains((string) $server->display_name, 'Market (Die Siedler')
                ) {
                    $server->display_name = $this->displayName(null, $server->server_id);
                }

                return $server;
            });
    }

    /**
     * @throws MarketOperationException
     */
    private function applyAccountVerification(MarketServerConnection $server): void
    {
        $account = Account::find($server->account_id);

        if (! $account) {
            throw MarketOperationException::unprocessable(__('ui.market.api.account_not_found'));
        }

        $detection = $this->verification->detectServerForAccount($account);
        $detectedServerId = $detection['detected_server_id'] ? strtolower((string) $detection['detected_server_id']) : null;

        if ($detectedServerId && $detectedServerId !== strtolower((string) $server->server_id)) {
            throw MarketOperationException::unprocessable(__('ui.market.api.account_wrong_server', [
                'username' => $account->username,
                'detected' => $detectedServerId,
                'server' => $server->server_id,
            ]));
        }

        $verification = $this->verification->verifyAccountServerMatch($account, $server);
        $server->verification_status = $verification['status'];

        if ($verification['status'] === 'verified') {
            $server->last_error = null;

            if (in_array($server->sync_status, ['error', 'not_configured'], true)) {
                $server->sync_status = 'connected';
            }
        }
    }

    private function displayName(?string $gameWorld, string $serverId): string
    {
        $suffix = (string) config('market.display_name_suffix', 'Settlers Market');

        return ($gameWorld !== null && $gameWorld !== '')
            ? "{$gameWorld} {$suffix}"
            : strtoupper($serverId).' '.$suffix;
    }

    private function invalidate(string $serverId): void
    {
        $this->cache->bumpDataVersion($serverId);
        $this->cache->bumpDataVersion(MarketCacheService::GLOBAL_SERVER);
    }
}
