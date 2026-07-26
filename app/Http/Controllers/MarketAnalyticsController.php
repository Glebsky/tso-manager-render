<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\PublicServerResource;
use App\Models\Account;
use App\Models\MarketHistory;
use App\Models\MarketOffer;
use App\Models\MarketServerConnection;
use App\Models\MarketSyncLog;
use App\Models\Setting;
use App\Services\Lang\GameTranslationResolver;
use App\Services\MarketCacheService;
use App\Services\MarketServerVerificationService;
use App\Services\MarketSyncService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketAnalyticsController extends Controller
{
    private MarketSyncService $syncService;

    private GameTranslationResolver $gameTranslations;

    private MarketServerVerificationService $verificationService;

    private MarketCacheService $cacheService;

    public function __construct(
        MarketSyncService $syncService,
        GameTranslationResolver $gameTranslations,
        MarketServerVerificationService $verificationService,
        MarketCacheService $cacheService
    ) {
        $this->syncService = $syncService;
        $this->gameTranslations = $gameTranslations;
        $this->verificationService = $verificationService;
        $this->cacheService = $cacheService;
    }

    /**
     * Resolve target server_id from request or fallback to first available.
     */
    private function resolveServerId(Request $request): string
    {
        return $this->cacheService->resolveServerId($request->input('server_id'));
    }

    /**
     * Resolve the display name for a resource id at read time.
     */
    private function resourceName(?string $itemId, ?string $legacyName): string
    {
        if ($itemId === null || $itemId === '') {
            return (string) ($legacyName ?? '');
        }

        $fallback = ($legacyName !== null && $legacyName !== '') ? $legacyName : null;

        return $this->gameTranslations->name('RES', $itemId, $fallback);
    }

    /**
     * Get server connection presets.
     */
    private function getServerPresets(): array
    {
        return [
            ['server_id' => 'ru', 'locale' => 'RU', 'display_name' => 'RU Settlers Market'],
            ['server_id' => 'de', 'locale' => 'DE', 'display_name' => 'DE Settlers Market'],
            ['server_id' => 'en', 'locale' => 'EN', 'display_name' => 'EN Settlers Market'],
            ['server_id' => 'us', 'locale' => 'EN', 'display_name' => 'US Settlers Market'],
            ['server_id' => 'fr', 'locale' => 'FR', 'display_name' => 'FR Settlers Market'],
            ['server_id' => 'pl', 'locale' => 'PL', 'display_name' => 'PL Settlers Market'],
            ['server_id' => 'es', 'locale' => 'ES', 'display_name' => 'ES Settlers Market'],
        ];
    }

    // ==========================================
    // SERVER CONNECTIONS & SETTINGS MANAGEMENT
    // ==========================================

    public function getServers(): JsonResponse
    {
        $servers = MarketServerConnection::with('account:id,username,nickname,region,status,zone_data')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($server) {
                if ($server->account && $server->account->server_name) {
                    $worldSlug = \Illuminate\Support\Str::slug($server->account->server_name, '_');
                    if (! empty($worldSlug)) {
                        $worldServerId = strtolower($server->account->region).'_'.$worldSlug;
                        if ($server->server_id !== $worldServerId && ! MarketServerConnection::where('server_id', $worldServerId)->where('id', '!=', $server->id)->exists()) {
                            $server->server_id = $worldServerId;
                            $server->save();
                        }
                    }
                    $server->display_name = "{$server->account->server_name} Settlers Market";
                } elseif (str_contains($server->display_name, 'Market (The Settlers') || str_contains($server->display_name, 'Market (Die Siedler')) {
                    $server->display_name = strtoupper($server->server_id).' Settlers Market';
                }

                return $server;
            });

        $accounts = Account::select('id', 'username', 'nickname', 'region', 'status', 'zone_data')
            ->latest()
            ->get();

        $syncInterval = (string) Setting::get('market_sync_interval', '15');
        $customIntervalMinutes = (int) Setting::get('market_custom_interval_minutes', 15);

        $firstAccountId = MarketServerConnection::whereNotNull('account_id')->value('account_id');
        $lastSyncTime = MarketSyncLog::where('status', 'SUCCESS')->latest()->value('created_at');

        return response()->json([
            'servers' => $servers,
            'accounts' => $accounts,
            'presets' => $this->getServerPresets(),
            'settings' => [
                'account_id' => $firstAccountId ? (int) $firstAccountId : null,
                'sync_interval' => $syncInterval,
                'custom_interval_minutes' => $customIntervalMinutes,
            ],
            'connection_status' => $servers->contains(fn ($s) => $s->sync_status === 'connected') ? 'Connected' : 'Disconnected',
            'last_sync' => $lastSyncTime ? $lastSyncTime->toIso8601String() : 'Never',
        ]);
    }

    public function getPublicServers(): JsonResponse
    {
        $data = $this->cacheService->remember('global', 'public_servers', [], 300, function () {
            $servers = MarketServerConnection::whereNotNull('account_id')
                ->whereHas('account')
                ->with('account:id,username,nickname,region,status,zone_data')
                ->select('id', 'server_id', 'locale', 'display_name', 'sync_status', 'account_id')
                ->orderBy('id')
                ->get();

            return PublicServerResource::collection($servers)->resolve();
        });

        return response()->json($data);
    }

    public function storeServer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'required|integer|exists:accounts,id',
        ]);

        $account = Account::findOrFail($validated['account_id']);
        $detection = $this->verificationService->detectServerForAccount($account);

        if (empty($detection['detected_server_id'])) {
            return response()->json([
                'success' => false,
                'message' => __('ui.market.api.account_no_region', ['username' => $account->username]),
            ], 422);
        }

        $serverId = strtolower($detection['detected_server_id']);
        $locale = strtoupper((string) ($detection['detected_locale'] ?? $serverId));

        if (MarketServerConnection::where('server_id', $serverId)->exists()) {
            $worldOrServer = $account->server_name ?: strtoupper($serverId);

            return response()->json([
                'success' => false,
                'message' => __('ui.market.api.server_exists', ['server' => $worldOrServer]),
            ], 422);
        }

        $gameWorld = $account->server_name;
        $displayName = $gameWorld
            ? "{$gameWorld} Settlers Market"
            : strtoupper($serverId).' Settlers Market';

        $server = MarketServerConnection::create([
            'server_id' => $serverId,
            'locale' => $locale,
            'display_name' => $displayName,
            'account_id' => $account->id,
            'verification_status' => 'verified',
            'sync_status' => 'connected',
        ]);

        $this->cacheService->bumpDataVersion($serverId);
        $this->cacheService->bumpDataVersion('global');

        return response()->json([
            'success' => true,
            'message' => __('ui.market.api.server_created', ['server' => $serverId, 'username' => $account->username]),
            'server' => $server->load('account:id,username,nickname,region,status'),
        ]);
    }

    public function updateServer(Request $request, MarketServerConnection $server): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'nullable|integer|exists:accounts,id',
            'sync_status' => 'nullable|string|in:not_configured,syncing,connected,error,disabled',
        ]);

        if (array_key_exists('account_id', $validated)) {
            $server->account_id = $validated['account_id'];
        }
        if (isset($validated['sync_status'])) {
            $server->sync_status = $validated['sync_status'];
        }

        if (! empty($server->account_id)) {
            $account = Account::find($server->account_id);
            if (! $account) {
                return response()->json([
                    'success' => false,
                    'message' => __('ui.market.api.account_not_found'),
                ], 422);
            }

            $detection = $this->verificationService->detectServerForAccount($account);
            $detectedServerId = $detection['detected_server_id'] ? strtolower($detection['detected_server_id']) : null;

            if ($detectedServerId && $detectedServerId !== strtolower($server->server_id)) {
                return response()->json([
                    'success' => false,
                    'message' => __('ui.market.api.account_wrong_server', ['username' => $account->username, 'detected' => $detectedServerId, 'server' => $server->server_id]),
                ], 422);
            }

            $verification = $this->verificationService->verifyAccountServerMatch($account, $server);
            $server->verification_status = $verification['status'];
            if ($verification['status'] === 'verified') {
                $server->last_error = null;
                if (in_array($server->sync_status, ['error', 'not_configured'], true)) {
                    $server->sync_status = 'connected';
                }
            }
        } else {
            $server->verification_status = 'unverified';
            $server->sync_status = 'not_configured';
        }

        $server->save();

        $this->cacheService->bumpDataVersion($server->server_id);
        $this->cacheService->bumpDataVersion('global');

        return response()->json([
            'success' => true,
            'message' => __('ui.market.api.server_updated'),
            'server' => $server->load('account:id,username,nickname,region,status'),
        ]);
    }

    public function deleteServer(MarketServerConnection $server): JsonResponse
    {
        $serverId = $server->server_id;
        $server->delete();

        $this->cacheService->bumpDataVersion($serverId);
        $this->cacheService->bumpDataVersion('global');

        return response()->json([
            'success' => true,
            'message' => __('ui.market.api.server_deleted'),
        ]);
    }

    public function verifyServerAccount(MarketServerConnection $server): JsonResponse
    {
        if (empty($server->account_id)) {
            $server->update([
                'verification_status' => 'unverified',
                'last_error' => __('ui.market.api.no_account_assigned'),
            ]);

            return response()->json([
                'success' => false,
                'status' => 'unverified',
                'message' => __('ui.market.api.no_account_assigned'),
                'server' => $server->load('account:id,username,nickname,region,status'),
            ]);
        }

        $account = Account::find($server->account_id);
        if (! $account) {
            $server->update([
                'verification_status' => 'error',
                'last_error' => __('ui.market.api.account_not_found'),
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => __('ui.market.api.account_not_found'),
                'server' => $server->load('account:id,username,nickname,region,status'),
            ], 422);
        }

        $result = $this->verificationService->verifyAccountServerMatch($account, $server);
        $server->verification_status = $result['status'];
        if ($result['status'] === 'mismatch') {
            $server->last_error = $result['message'];
        } elseif ($result['status'] === 'verified' && $server->last_error && str_contains($server->last_error, 'mismatch')) {
            $server->last_error = null;
        }

        $server->save();

        return response()->json([
            'success' => $result['status'] === 'verified',
            'status' => $result['status'],
            'message' => $result['message'],
            'detected_server' => $result['detected_server'] ?? null,
            'server' => $server->load('account:id,username,nickname,region,status'),
        ]);
    }

    public function syncServerNow(MarketServerConnection $server): JsonResponse
    {
        if (empty($server->account_id)) {
            return response()->json([
                'success' => false,
                'message' => __('ui.market.api.assign_account_first'),
            ], 422);
        }

        $account = Account::find($server->account_id);
        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => __('ui.market.api.account_not_found'),
            ], 422);
        }

        try {
            $result = $this->syncService->sync($account, $server->server_id);

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('ui.market.api.sync_failed', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    public function getSettings(): JsonResponse
    {
        return $this->getServers();
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sync_interval' => 'required|string|in:5,15,30,60,custom',
            'custom_interval_minutes' => 'nullable|integer|min:1',
        ]);

        Setting::set('market_sync_interval', $validated['sync_interval']);
        Setting::set('market_custom_interval_minutes', $validated['custom_interval_minutes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Market settings updated.',
        ]);
    }

    public function syncNow(Request $request): JsonResponse
    {
        $serverId = $this->resolveServerId($request);
        $server = MarketServerConnection::where('server_id', $serverId)->first();

        if (! $server || empty($server->account_id)) {
            return response()->json([
                'success' => false,
                'message' => "No account configured for server [{$serverId}].",
            ], 422);
        }

        return $this->syncServerNow($server);
    }

    // ==========================================
    // MARKET ANALYTICS DATA ENDPOINTS
    // ==========================================

    public function getGoods(Request $request): JsonResponse
    {
        $serverId = $this->resolveServerId($request);

        $goods = $this->cacheService->remember($serverId, 'goods', [], 1800, function () use ($serverId) {
            $fromOffers = MarketOffer::where('server_id', $serverId)->select('item_id', 'item_name');
            $fromHistory = MarketHistory::where('server_id', $serverId)->select('item_id', 'item_name');

            return $fromOffers->union($fromHistory)
                ->distinct()
                ->orderBy('item_name')
                ->get()
                ->unique('item_id')
                ->map(fn ($row) => [
                    'item_id' => $row->item_id,
                    'item_name' => $this->resourceName($row->item_id, $row->item_name),
                ])
                ->sortBy('item_name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->toArray();
        });

        return response()->json($goods);
    }

    public function getTargets(Request $request): JsonResponse
    {
        $serverId = $this->resolveServerId($request);
        $itemId = (string) $request->input('item_id');

        if (empty($itemId)) {
            return response()->json([]);
        }

        $targets = $this->cacheService->remember($serverId, 'targets', ['item_id' => $itemId], 1800, function () use ($serverId, $itemId) {
            $fromOffers = MarketOffer::where('server_id', $serverId)
                ->where('item_id', $itemId)
                ->select('target_item_id', 'target_item_name');
            $fromHistory = MarketHistory::where('server_id', $serverId)
                ->where('item_id', $itemId)
                ->select('target_item_id', 'target_item_name');

            return $fromOffers->union($fromHistory)
                ->distinct()
                ->orderBy('target_item_name')
                ->get()
                ->unique('target_item_id')
                ->map(fn ($row) => [
                    'target_item_id' => $row->target_item_id,
                    'target_item_name' => $this->resourceName($row->target_item_id, $row->target_item_name),
                ])
                ->sortBy('target_item_name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->toArray();
        });

        return response()->json($targets);
    }

    /**
     * Get popular items per server and period, cached per server & period.
     */
    private function getPopularItems(string $serverId, ?Carbon $dateFilter, string $period): array
    {
        return $this->cacheService->remember($serverId, 'popular', ['period' => $period], 900, function () use ($serverId, $dateFilter) {
            $popularQuery = MarketHistory::where('server_id', $serverId)
                ->selectRaw('item_id, item_name, count(*) as offers_count, count(distinct player_id) as sellers_count, sum(volume) as total_volume');
            if ($dateFilter) {
                $popularQuery->where('collected_at', '>=', $dateFilter);
            }

            return $popularQuery->groupBy('item_id', 'item_name')
                ->orderBy('offers_count', 'desc')
                ->orderBy('total_volume', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($row) {
                    $row->item_name = $this->resourceName($row->item_id, $row->item_name);

                    return $row;
                })
                ->toArray();
        });
    }

    public function getAnalytics(Request $request): JsonResponse
    {
        $serverId = $this->resolveServerId($request);
        $itemId = $request->input('item_id');
        $targetItemId = $request->input('target_item_id');
        $period = $request->input('period', 'all');

        $now = Carbon::now();
        $dateFilter = match ($period) {
            '1d' => $now->copy()->subDay(),
            '7d' => $now->copy()->subDays(7),
            '30d' => $now->copy()->subDays(30),
            '1y' => $now->copy()->subYear(),
            default => null,
        };

        if (empty($itemId) || empty($targetItemId)) {
            $limit = (int) $request->input('limit', 100);
            $page = (int) $request->input('page', 1);

            $cachedOverview = $this->cacheService->remember($serverId, 'analytics_overview', ['period' => $period], 300, function () use ($serverId, $dateFilter, $period) {
                $popular = $this->getPopularItems($serverId, $dateFilter, $period);

                $activeOffersQuery = MarketOffer::where('server_id', $serverId)
                    ->where('created_at', '>=', now()->subHours(6))
                    ->orderBy('created_at', 'desc');

                $allOffers = $activeOffersQuery->get()
                    ->map(function ($offer) {
                        $expiresAt = $offer->created_at->copy()->addHours(6);

                        return [
                            'id' => $offer->id,
                            'server_id' => $offer->server_id,
                            'offer_id' => $offer->offer_id,
                            'sender_name' => $offer->sender_name,
                            'item_id' => $offer->item_id,
                            'item_name' => $this->resourceName($offer->item_id, $offer->item_name),
                            'amount' => $offer->amount,
                            'target_item_id' => $offer->target_item_id,
                            'target_item_name' => $this->resourceName($offer->target_item_id, $offer->target_item_name),
                            'target_amount' => $offer->target_amount,
                            'price' => round((float) $offer->price, 4),
                            'volume' => $offer->volume,
                            'lots_remaining' => $offer->lots_remaining,
                            'created_at' => $offer->created_at->toIso8601String(),
                            'expires_at' => $expiresAt->toIso8601String(),
                        ];
                    })
                    ->toArray();

                return [
                    'server_id' => $serverId,
                    'popular' => $popular,
                    'all_active_offers' => $allOffers,
                    'total_active_count' => count($allOffers),
                ];
            });

            $totalActive = $cachedOverview['total_active_count'];
            $offset = max(0, ($page - 1) * $limit);
            $slicedOffers = array_slice($cachedOverview['all_active_offers'], $offset, $limit);

            $currentTime = now();
            foreach ($slicedOffers as &$offer) {
                if (isset($offer['expires_at'])) {
                    $expiresAt = Carbon::parse($offer['expires_at']);
                    $timeLeft = $currentTime->diffInSeconds($expiresAt, false);
                    $offer['time_left'] = $timeLeft > 0 ? $timeLeft : 0;
                }
            }

            return response()->json([
                'server_id' => $cachedOverview['server_id'],
                'popular' => $cachedOverview['popular'],
                'active_offers' => $slicedOffers,
                'total_active_count' => $totalActive,
                'page' => $page,
                'has_more' => ($offset + $limit) < $totalActive,
            ]);
        }

        $pairPayload = $this->cacheService->remember($serverId, 'analytics_pair', ['item_id' => $itemId, 'target_item_id' => $targetItemId, 'period' => $period], 900, function () use ($serverId, $itemId, $targetItemId, $dateFilter, $period) {
            $popular = $this->getPopularItems($serverId, $dateFilter, $period);

            // Determine dynamic group-by expression based on dataset date span
            $groupByExpression = 'day';
            $minDateStr = MarketHistory::where('server_id', $serverId)
                ->where('item_id', $itemId)
                ->where('target_item_id', $targetItemId)
                ->when($dateFilter, function ($q) use ($dateFilter) {
                    $q->where('collected_at', '>=', $dateFilter);
                })
                ->min('collected_at');

            $maxDateStr = MarketHistory::where('server_id', $serverId)
                ->where('item_id', $itemId)
                ->where('target_item_id', $targetItemId)
                ->when($dateFilter, function ($q) use ($dateFilter) {
                    $q->where('collected_at', '>=', $dateFilter);
                })
                ->max('collected_at');

            if ($minDateStr && $maxDateStr) {
                $daysSpan = Carbon::parse($minDateStr)->startOfDay()->diffInDays(Carbon::parse($maxDateStr)->startOfDay());
                if ($period === '1d' || $daysSpan <= 2) {
                    $groupByExpression = 'hour';
                } elseif ($daysSpan <= 90) {
                    $groupByExpression = 'day';
                } elseif ($daysSpan <= 730) {
                    $groupByExpression = 'week';
                } else {
                    $groupByExpression = 'month';
                }
            } else {
                $groupByExpression = ($period === '1d') ? 'hour' : 'day';
            }

            // 2. Active & closed history stats for selected server
            $statsQuery = MarketHistory::where('server_id', $serverId)
                ->where('item_id', $itemId)
                ->where('target_item_id', $targetItemId);

            if ($dateFilter) {
                $statsQuery->where('collected_at', '>=', $dateFilter);
            }

            $stats = $statsQuery->selectRaw('avg(price) as average_price, min(price) as min_price, max(price) as max_price')
                ->first();

            // Latest price (current)
            $current = MarketHistory::where('server_id', $serverId)
                ->where('item_id', $itemId)
                ->where('target_item_id', $targetItemId)
                ->orderBy('collected_at', 'desc')
                ->value('price');

            // Fetch mirrored stats
            $mirroredStatsQuery = MarketHistory::where('server_id', $serverId)
                ->where('item_id', $targetItemId)
                ->where('target_item_id', $itemId);

            if ($dateFilter) {
                $mirroredStatsQuery->where('collected_at', '>=', $dateFilter);
            }

            $mirroredStats = $mirroredStatsQuery->selectRaw('avg(price) as average_price, min(price) as min_price, max(price) as max_price')
                ->first();

            $mirroredCurrent = MarketHistory::where('server_id', $serverId)
                ->where('item_id', $targetItemId)
                ->where('target_item_id', $itemId)
                ->orderBy('collected_at', 'desc')
                ->value('price');

            // Real-time live active market info for the selected pair
            $activeOffersForPair = MarketOffer::where('server_id', $serverId)
                ->where('item_id', $itemId)
                ->where('target_item_id', $targetItemId)
                ->where('created_at', '>=', now()->subHours(6))
                ->get();

            $activeInfo = [
                'volume' => (int) $activeOffersForPair->sum('volume'),
                'offers_count' => $activeOffersForPair->count(),
                'sellers_count' => $activeOffersForPair->pluck('player_id')->unique()->count(),
            ];

            // Period aggregate summary
            $periodSummary = MarketHistory::where('server_id', $serverId)
                ->where('item_id', $itemId)
                ->where('target_item_id', $targetItemId)
                ->when($dateFilter, function ($q) use ($dateFilter) {
                    $q->where('collected_at', '>=', $dateFilter);
                })
                ->selectRaw('sum(volume) as total_volume, count(*) as offers_count, count(distinct player_id) as sellers_count')
                ->first();

            $periodInfo = [
                'volume' => (int) ($periodSummary->total_volume ?? 0),
                'offers_count' => (int) ($periodSummary->offers_count ?? 0),
                'sellers_count' => (int) ($periodSummary->sellers_count ?? 0),
            ];

            // 3. Historical data
            $driver = DB::connection()->getDriverName();

            $buildHistoryQuery = function ($item, $target, $dateFilter, $groupByExpression, $driver) use ($serverId) {
                $query = MarketHistory::where('server_id', $serverId)
                    ->where('item_id', $item)
                    ->where('target_item_id', $target);

                if ($dateFilter) {
                    $query->where('collected_at', '>=', $dateFilter);
                }

                if ($driver === 'pgsql') {
                    $trunc = "date_trunc('{$groupByExpression}', collected_at)";
                    $query->selectRaw("{$trunc} as time_bucket, avg(price) as price, sum(volume) as volume, count(distinct player_id) as sellers_count, count(*) as offers_count, round(avg(amount)) as avg_amount, round(avg(target_amount)) as avg_target_amount")
                        ->groupBy('time_bucket')
                        ->orderBy('time_bucket', 'asc');
                } elseif ($driver === 'mysql') {
                    if ($groupByExpression === 'hour') {
                        $selectBucket = "DATE_FORMAT(collected_at, '%Y-%m-%d %H:00:00')";
                    } elseif ($groupByExpression === 'week') {
                        $selectBucket = "DATE_FORMAT(DATE_SUB(collected_at, INTERVAL WEEKDAY(collected_at) DAY), '%Y-%m-%d')";
                    } elseif ($groupByExpression === 'month') {
                        $selectBucket = "DATE_FORMAT(collected_at, '%Y-%m-01')";
                    } else {
                        $selectBucket = "DATE_FORMAT(collected_at, '%Y-%m-%d')";
                    }
                    $query->selectRaw("{$selectBucket} as time_bucket, avg(price) as price, sum(volume) as volume, count(distinct player_id) as sellers_count, count(*) as offers_count, round(avg(amount)) as avg_amount, round(avg(target_amount)) as avg_target_amount")
                        ->groupBy('time_bucket')
                        ->orderBy('time_bucket', 'asc');
                } else {
                    if ($groupByExpression === 'hour') {
                        $selectBucket = "strftime('%Y-%m-%d %H:00:00', collected_at)";
                    } elseif ($groupByExpression === 'week') {
                        $selectBucket = "date(collected_at, 'weekday 0', '-6 days')";
                    } elseif ($groupByExpression === 'month') {
                        $selectBucket = "strftime('%Y-%m-01', collected_at)";
                    } else {
                        $selectBucket = "strftime('%Y-%m-%d', collected_at)";
                    }
                    $query->selectRaw("{$selectBucket} as time_bucket, avg(price) as price, sum(volume) as volume, count(distinct player_id) as sellers_count, count(*) as offers_count, round(avg(amount)) as avg_amount, round(avg(target_amount)) as avg_target_amount")
                        ->groupBy('time_bucket')
                        ->orderBy('time_bucket', 'asc');
                }

                return $query->get()->map(function ($item) use ($groupByExpression) {
                    $dateVal = is_string($item->time_bucket) ? Carbon::parse($item->time_bucket) : new Carbon($item->time_bucket);
                    if ($groupByExpression === 'hour') {
                        $formattedDate = $dateVal->format('d.m.Y H:i');
                    } elseif ($groupByExpression === 'month') {
                        $formattedDate = $dateVal->format('m.Y');
                    } else {
                        $formattedDate = $dateVal->format('d.m.Y');
                    }

                    return [
                        'collected_at' => $formattedDate,
                        'price' => round((float) $item->price, 4),
                        'volume' => (int) $item->volume,
                        'sellers_count' => (int) $item->sellers_count,
                        'offers_count' => (int) $item->offers_count,
                        'avg_amount' => (int) round((float) ($item->avg_amount ?? 1)),
                        'avg_target_amount' => (int) round((float) ($item->avg_target_amount ?? 1)),
                    ];
                });
            };

            $history = $buildHistoryQuery($itemId, $targetItemId, $dateFilter, $groupByExpression, $driver);

            $mirroredHistory = collect([]);
            if ($itemId && $targetItemId) {
                $mirroredHistory = $buildHistoryQuery($targetItemId, $itemId, $dateFilter, $groupByExpression, $driver);
            }

            $mirroredStatsData = null;
            if ($mirroredStats && $mirroredStats->average_price !== null) {
                $mirroredStatsData = [
                    'average' => round((float) ($mirroredStats->average_price ?? 0), 2),
                    'minimum' => round((float) ($mirroredStats->min_price ?? 0), 2),
                    'maximum' => round((float) ($mirroredStats->max_price ?? 0), 2),
                    'current' => round((float) ($mirroredCurrent ?? 0), 2),
                ];
            }

            return [
                'server_id' => $serverId,
                'popular' => $popular,
                'stats' => [
                    'average' => round((float) ($stats->average_price ?? 0), 2),
                    'minimum' => round((float) ($stats->min_price ?? 0), 2),
                    'maximum' => round((float) ($stats->max_price ?? 0), 2),
                    'current' => round((float) ($current ?? 0), 2),
                ],
                'history' => $history->toArray(),
                'active_info' => $activeInfo,
                'period_info' => $periodInfo,
                'mirrored_stats' => $mirroredStatsData,
                'mirrored_history' => $mirroredHistory->isEmpty() ? null : $mirroredHistory->toArray(),
            ];
        });

        return response()->json($pairPayload);
    }

    public function getLogs(Request $request): JsonResponse
    {
        $serverId = $request->input('server_id');
        $limit = (int) $request->input('limit', 10);

        $query = MarketSyncLog::orderBy('created_at', 'desc');
        if (! empty($serverId)) {
            $query->where('server_id', $serverId);
        }

        $paginator = $query->paginate($limit);

        return response()->json([
            'data' => collect($paginator->items())->map(function ($log) {
                return [
                    'date' => $log->created_at->toIso8601String(),
                    'server_id' => $log->server_id,
                    'action' => $log->action,
                    'status' => $log->status,
                    'message' => $log->message,
                ];
            }),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function getArbitrage(Request $request): JsonResponse
    {
        $serverId = $this->resolveServerId($request);

        $result = $this->cacheService->remember($serverId, 'arbitrage', [], 900, function () use ($serverId) {
            $offers = MarketOffer::where('server_id', $serverId)
                ->where('created_at', '>=', now()->subHours(6))
                ->get();
            $byPair = [];

            foreach ($offers as $offer) {
                $from = $offer->target_item_id;
                $to = $offer->item_id;
                $byPair[$from][$to][] = [
                    'offer_id' => $offer->offer_id,
                    'sender_name' => $offer->sender_name,
                    'item_id' => $offer->item_id,
                    'item_name' => $this->resourceName($offer->item_id, $offer->item_name),
                    'amount' => $offer->amount,
                    'target_item_id' => $offer->target_item_id,
                    'target_item_name' => $this->resourceName($offer->target_item_id, $offer->target_item_name),
                    'target_amount' => $offer->target_amount,
                    'lots_remaining' => $offer->lots_remaining,
                ];
            }

            $loops = [];
            $resources = array_keys($byPair);

            foreach ($resources as $A) {
                if (! isset($byPair[$A])) {
                    continue;
                }

                foreach ($byPair[$A] as $B => $t1List) {
                    if ($B === $A) {
                        continue;
                    }

                    // 1. 2-step loops: A -> B -> A
                    if (isset($byPair[$B][$A])) {
                        $t2List = $byPair[$B][$A];
                        foreach ($t1List as $t1) {
                            foreach ($t2List as $t2) {
                                $bestProfit = -99999999;
                                $best_x = 0;
                                $best_y = 0;

                                $maxX = $t1['lots_remaining'];
                                $maxY = $t2['lots_remaining'];

                                for ($x = 1; $x <= $maxX; $x++) {
                                    $gotB = $x * $t1['amount'];
                                    $neededForT2Lot = $t2['target_amount'];
                                    if ($neededForT2Lot <= 0) {
                                        continue;
                                    }
                                    $y = (int) floor($gotB / $neededForT2Lot);
                                    if ($y > $maxY) {
                                        $y = $maxY;
                                    }
                                    if ($y <= 0) {
                                        continue;
                                    }

                                    $costA = $x * $t1['target_amount'];
                                    $returnedA = $y * $t2['amount'];
                                    $profitA = $returnedA - $costA;

                                    if ($profitA > $bestProfit) {
                                        $bestProfit = $profitA;
                                        $best_x = $x;
                                        $best_y = $y;
                                    }
                                }

                                if ($bestProfit > 0) {
                                    $gotB = $best_x * $t1['amount'];
                                    $usedB = $best_y * $t2['target_amount'];
                                    $leftoverB = $gotB - $usedB;

                                    $loops[] = [
                                        'type' => '2-step',
                                        'start_resource' => $A,
                                        'start_resource_name' => $t1['target_item_name'],
                                        'steps' => [
                                            [
                                                'step' => 1,
                                                'sender' => $t1['sender_name'],
                                                'offer_id' => $t1['offer_id'],
                                                'give_item' => $A,
                                                'give_name' => $t1['target_item_name'],
                                                'give_amount' => $best_x * $t1['target_amount'],
                                                'give_per_lot' => $t1['target_amount'],
                                                'receive_item' => $B,
                                                'receive_name' => $t1['item_name'],
                                                'receive_amount' => $best_x * $t1['amount'],
                                                'receive_per_lot' => $t1['amount'],
                                                'lots' => $best_x,
                                            ],
                                            [
                                                'step' => 2,
                                                'sender' => $t2['sender_name'],
                                                'offer_id' => $t2['offer_id'],
                                                'give_item' => $B,
                                                'give_name' => $t2['target_item_name'],
                                                'give_amount' => $best_y * $t2['target_amount'],
                                                'give_per_lot' => $t2['target_amount'],
                                                'receive_item' => $A,
                                                'receive_name' => $t2['item_name'],
                                                'receive_amount' => $best_y * $t2['amount'],
                                                'receive_per_lot' => $t2['amount'],
                                                'lots' => $best_y,
                                            ],
                                        ],
                                        'profit' => [
                                            'item_id' => $A,
                                            'item_name' => $t1['target_item_name'],
                                            'amount' => $bestProfit,
                                        ],
                                        'leftovers' => $leftoverB > 0 ? [
                                            [
                                                'item_id' => $B,
                                                'item_name' => $t1['item_name'],
                                                'amount' => $leftoverB,
                                            ],
                                        ] : [],
                                    ];
                                }
                            }
                        }
                    }

                    // 2. 3-step loops: A -> B -> C -> A
                    if (isset($byPair[$B])) {
                        foreach ($byPair[$B] as $C => $t2List) {
                            if ($C === $A || $C === $B) {
                                continue;
                            }

                            if (isset($byPair[$C][$A])) {
                                $t3List = $byPair[$C][$A];
                                foreach ($t1List as $t1) {
                                    foreach ($t2List as $t2) {
                                        foreach ($t3List as $t3) {
                                            $bestProfit = -99999999;
                                            $best_x = 0;
                                            $best_y = 0;
                                            $best_z = 0;

                                            $maxX = $t1['lots_remaining'];
                                            $maxY = $t2['lots_remaining'];
                                            $maxZ = $t3['lots_remaining'];

                                            for ($x = 1; $x <= $maxX; $x++) {
                                                $gotB = $x * $t1['amount'];
                                                $neededForT2Lot = $t2['target_amount'];
                                                if ($neededForT2Lot <= 0) {
                                                    continue;
                                                }
                                                $y = (int) floor($gotB / $neededForT2Lot);
                                                if ($y > $maxY) {
                                                    $y = $maxY;
                                                }
                                                if ($y <= 0) {
                                                    continue;
                                                }

                                                $gotC = $y * $t2['amount'];
                                                $neededForT3Lot = $t3['target_amount'];
                                                if ($neededForT3Lot <= 0) {
                                                    continue;
                                                }
                                                $z = (int) floor($gotC / $neededForT3Lot);
                                                if ($z > $maxZ) {
                                                    $z = $maxZ;
                                                }
                                                if ($z <= 0) {
                                                    continue;
                                                }

                                                $costA = $x * $t1['target_amount'];
                                                $returnedA = $z * $t3['amount'];
                                                $profitA = $returnedA - $costA;

                                                if ($profitA > $bestProfit) {
                                                    $bestProfit = $profitA;
                                                    $best_x = $x;
                                                    $best_y = $y;
                                                    $best_z = $z;
                                                }
                                            }

                                            if ($bestProfit > 0) {
                                                $gotB = $best_x * $t1['amount'];
                                                $usedB = $best_y * $t2['target_amount'];
                                                $leftoverB = $gotB - $usedB;

                                                $gotC = $best_y * $t2['amount'];
                                                $usedC = $best_z * $t3['target_amount'];
                                                $leftoverC = $gotC - $usedC;

                                                $leftovers = [];
                                                if ($leftoverB > 0) {
                                                    $leftovers[] = [
                                                        'item_id' => $B,
                                                        'item_name' => $t1['item_name'],
                                                        'amount' => $leftoverB,
                                                    ];
                                                }
                                                if ($leftoverC > 0) {
                                                    $leftovers[] = [
                                                        'item_id' => $C,
                                                        'item_name' => $t2['item_name'],
                                                        'amount' => $leftoverC,
                                                    ];
                                                }

                                                $loops[] = [
                                                    'type' => '3-step',
                                                    'start_resource' => $A,
                                                    'start_resource_name' => $t1['target_item_name'],
                                                    'steps' => [
                                                        [
                                                            'step' => 1,
                                                            'sender' => $t1['sender_name'],
                                                            'offer_id' => $t1['offer_id'],
                                                            'give_item' => $A,
                                                            'give_name' => $t1['target_item_name'],
                                                            'give_amount' => $best_x * $t1['target_amount'],
                                                            'give_per_lot' => $t1['target_amount'],
                                                            'receive_item' => $B,
                                                            'receive_name' => $t1['item_name'],
                                                            'receive_amount' => $best_x * $t1['amount'],
                                                            'receive_per_lot' => $t1['amount'],
                                                            'lots' => $best_x,
                                                        ],
                                                        [
                                                            'step' => 2,
                                                            'sender' => $t2['sender_name'],
                                                            'offer_id' => $t2['offer_id'],
                                                            'give_item' => $B,
                                                            'give_name' => $t2['target_item_name'],
                                                            'give_amount' => $best_y * $t2['target_amount'],
                                                            'give_per_lot' => $t2['target_amount'],
                                                            'receive_item' => $C,
                                                            'receive_name' => $t2['item_name'],
                                                            'receive_amount' => $best_y * $t2['amount'],
                                                            'receive_per_lot' => $t2['amount'],
                                                            'lots' => $best_y,
                                                        ],
                                                        [
                                                            'step' => 3,
                                                            'sender' => $t3['sender_name'],
                                                            'offer_id' => $t3['offer_id'],
                                                            'give_item' => $C,
                                                            'give_name' => $t3['target_item_name'],
                                                            'give_amount' => $best_z * $t3['target_amount'],
                                                            'give_per_lot' => $t3['target_amount'],
                                                            'receive_item' => $A,
                                                            'receive_name' => $t3['item_name'],
                                                            'receive_amount' => $best_z * $t3['amount'],
                                                            'receive_per_lot' => $t3['amount'],
                                                            'lots' => $best_z,
                                                        ],
                                                    ],
                                                    'profit' => [
                                                        'item_id' => $A,
                                                        'item_name' => $t1['target_item_name'],
                                                        'amount' => $bestProfit,
                                                    ],
                                                    'leftovers' => $leftovers,
                                                ];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            usort($loops, fn ($a, $b) => $b['profit']['amount'] <=> $a['profit']['amount']);

            return array_slice($loops, 0, 20);
        });

        return response()->json($result);
    }
}
