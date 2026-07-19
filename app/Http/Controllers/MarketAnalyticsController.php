<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\MarketHistory;
use App\Models\MarketOffer;
use App\Models\MarketSyncLog;
use App\Models\Setting;
use App\Services\MarketSyncService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketAnalyticsController extends Controller
{
    private MarketSyncService $syncService;

    public function __construct(MarketSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    private function loadSettings(): array
    {
        $accountIdVal = Setting::get('market_account_id', null);

        return [
            'account_id' => $accountIdVal !== null ? (int) $accountIdVal : null,
            'sync_interval' => (string) Setting::get('market_sync_interval', '15'),
            'custom_interval_minutes' => (int) Setting::get('market_custom_interval_minutes', 15),
        ];
    }

    private function saveSettings(array $settings): void
    {
        Setting::set('market_account_id', $settings['account_id']);
        Setting::set('market_sync_interval', $settings['sync_interval']);
        Setting::set('market_custom_interval_minutes', $settings['custom_interval_minutes'] ?? null);
    }

    public function getSettings()
    {
        $settings = $this->loadSettings();
        $accounts = Account::select('id', 'username', 'nickname')->latest()->get();

        $accountId = $settings['account_id'] ?? null;
        $connectionStatus = 'Disconnected';
        $lastSyncStr = 'Never';

        if ($accountId) {
            $account = Account::find($accountId);
            if ($account) {
                // If account is online, we say Connected. Otherwise use account status
                $connectionStatus = ($account->status === 'online') ? 'Connected' : 'Error';
            }

            // Find last successful sync log
            $lastLog = MarketSyncLog::where('account_id', $accountId)
                ->where('status', 'SUCCESS')
                ->latest()
                ->first();
            if ($lastLog) {
                $lastSyncStr = $lastLog->created_at->format('d.m.Y H:i:s');
            }
        }

        return response()->json([
            'settings' => $settings,
            'accounts' => $accounts,
            'connection_status' => $connectionStatus,
            'last_sync' => $lastSyncStr,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'nullable|integer|exists:accounts,id',
            'sync_interval' => 'required|string|in:5,15,30,60,custom',
            'custom_interval_minutes' => 'nullable|integer|min:1',
        ]);

        $this->saveSettings($validated);

        return response()->json([
            'success' => true,
            'message' => 'Market settings updated.',
        ]);
    }

    public function syncNow(Request $request)
    {
        $settings = $this->loadSettings();
        $accountId = $settings['account_id'] ?? null;

        if (empty($accountId)) {
            return response()->json([
                'success' => false,
                'message' => 'Please select an account in Settings first.',
            ], 422);
        }

        $account = Account::find($accountId);
        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'Configured account not found.',
            ], 422);
        }

        try {
            $result = $this->syncService->sync($account);

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Synchronization failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getGoods()
    {
        // Get list of unique items from both active offers and full trade history
        $fromOffers = MarketOffer::select('item_id', 'item_name');
        $fromHistory = MarketHistory::select('item_id', 'item_name');

        $goods = $fromOffers->union($fromHistory)
            ->distinct()
            ->orderBy('item_name')
            ->get()
            ->unique('item_id')
            ->values();

        return response()->json($goods);
    }

    public function getTargets(Request $request)
    {
        $itemId = $request->input('item_id');

        if (empty($itemId)) {
            return response()->json([]);
        }

        // Get target items from both active offers and full trade history
        $fromOffers = MarketOffer::where('item_id', $itemId)
            ->select('target_item_id', 'target_item_name');
        $fromHistory = MarketHistory::where('item_id', $itemId)
            ->select('target_item_id', 'target_item_name');

        $targets = $fromOffers->union($fromHistory)
            ->distinct()
            ->orderBy('target_item_name')
            ->get()
            ->unique('target_item_id')
            ->values();

        return response()->json($targets);
    }

    public function getAnalytics(Request $request)
    {
        $itemId = $request->input('item_id');
        $targetItemId = $request->input('target_item_id');
        $period = $request->input('period', 'all');

        // 3. Date filter calculation (moved up so stats queries can use it)
        $now = Carbon::now();
        $dateFilter = null;

        switch ($period) {
            case '1d':
                $dateFilter = $now->copy()->subDay();
                break;
            case '7d':
                $dateFilter = $now->copy()->subDays(7);
                break;
            case '30d':
                $dateFilter = $now->copy()->subDays(30);
                break;
            case '1y':
                $dateFilter = $now->copy()->subYear();
                break;
            case 'all':
            default:
                $dateFilter = null;
                break;
        }

        // Determine dynamic group-by expression based on dataset date span
        $groupByExpression = 'day';
        if ($itemId && $targetItemId) {
            $minDateStr = MarketHistory::where('item_id', $itemId)
                ->where('target_item_id', $targetItemId)
                ->when($dateFilter, function ($q) use ($dateFilter) {
                    $q->where('collected_at', '>=', $dateFilter);
                })
                ->min('collected_at');

            $maxDateStr = MarketHistory::where('item_id', $itemId)
                ->where('target_item_id', $targetItemId)
                ->when($dateFilter, function ($q) use ($dateFilter) {
                    $q->where('collected_at', '>=', $dateFilter);
                })
                ->max('collected_at');

            if ($minDateStr && $maxDateStr) {
                $daysSpan = Carbon::parse($minDateStr)->diffInDays(Carbon::parse($maxDateStr));
                if ($period === '1d') {
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
        }

        // 1. Most popular items (from history, both active and closed)
        $popularQuery = MarketHistory::selectRaw('item_id, item_name, count(*) as offers_count, count(distinct player_id) as sellers_count, sum(volume) as total_volume');
        if ($dateFilter) {
            $popularQuery->where('collected_at', '>=', $dateFilter);
        }
        $popular = $popularQuery->groupBy('item_id', 'item_name')
            ->orderBy('offers_count', 'desc')
            ->orderBy('total_volume', 'desc')
            ->limit(10)
            ->get();

        if (empty($itemId) || empty($targetItemId)) {
            $limit = (int) $request->input('limit', 100);
            $page = (int) $request->input('page', 1);
            $offset = ($page - 1) * $limit;

            $activeOffersQuery = MarketOffer::where('created_at', '>=', now()->subHours(6))
                ->orderBy('created_at', 'desc');
            $totalActive = $activeOffersQuery->count();

            $activeOffers = $activeOffersQuery->offset($offset)
                ->limit($limit)
                ->get()
                ->map(function ($offer) {
                    $now = now();
                    $expiresAt = $offer->created_at->copy()->addHours(6);
                    $timeLeft = $now->diffInSeconds($expiresAt, false);

                    return [
                        'id' => $offer->id,
                        'offer_id' => $offer->offer_id,
                        'sender_name' => $offer->sender_name,
                        'item_id' => $offer->item_id,
                        'item_name' => $offer->item_name,
                        'amount' => $offer->amount,
                        'target_item_id' => $offer->target_item_id,
                        'target_item_name' => $offer->target_item_name,
                        'target_amount' => $offer->target_amount,
                        'price' => round($offer->price, 4),
                        'volume' => $offer->volume,
                        'lots_remaining' => $offer->lots_remaining,
                        'created_at' => $offer->created_at->format('d.m.Y H:i'),
                        'time_left' => $timeLeft > 0 ? $timeLeft : 0,
                    ];
                });

            return response()->json([
                'popular' => $popular,
                'active_offers' => $activeOffers,
                'total_active_count' => $totalActive,
                'page' => $page,
                'has_more' => ($offset + $limit) < $totalActive,
            ]);
        }

        // 2. Active & closed history stats
        $statsQuery = MarketHistory::where('item_id', $itemId)
            ->where('target_item_id', $targetItemId);

        if ($dateFilter) {
            $statsQuery->where('collected_at', '>=', $dateFilter);
        }

        $stats = $statsQuery->selectRaw('avg(price) as average_price, min(price) as min_price, max(price) as max_price')
            ->first();

        // Latest price (current)
        $current = MarketHistory::where('item_id', $itemId)
            ->where('target_item_id', $targetItemId)
            ->orderBy('collected_at', 'desc')
            ->value('price');

        // Fetch mirrored stats if possible
        $mirroredStatsQuery = MarketHistory::where('item_id', $targetItemId)
            ->where('target_item_id', $itemId);

        if ($dateFilter) {
            $mirroredStatsQuery->where('collected_at', '>=', $dateFilter);
        }

        $mirroredStats = $mirroredStatsQuery->selectRaw('avg(price) as average_price, min(price) as min_price, max(price) as max_price')
            ->first();

        $mirroredCurrent = MarketHistory::where('item_id', $targetItemId)
            ->where('target_item_id', $itemId)
            ->orderBy('collected_at', 'desc')
            ->value('price');

        // Real-time live active market info for the selected pair
        $activeOffersForPair = MarketOffer::where('item_id', $itemId)
            ->where('target_item_id', $targetItemId)
            ->where('created_at', '>=', now()->subHours(6))
            ->get();

        $activeInfo = [
            'volume' => (int) $activeOffersForPair->sum('volume'),
            'offers_count' => $activeOffersForPair->count(),
            'sellers_count' => $activeOffersForPair->pluck('player_id')->unique()->count(),
        ];

        // Period aggregate summary for the selected pair over dateFilter
        $periodSummary = MarketHistory::where('item_id', $itemId)
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

        $buildHistoryQuery = function ($item, $target, $dateFilter, $groupByExpression, $driver) {
            $query = MarketHistory::where('item_id', $item)
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
                // sqlite or fallback
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
                    'price' => round($item->price, 4),
                    'volume' => (int) $item->volume,
                    'sellers_count' => (int) $item->sellers_count,
                    'offers_count' => (int) $item->offers_count,
                    'avg_amount' => (int) round($item->avg_amount ?? 1),
                    'avg_target_amount' => (int) round($item->avg_target_amount ?? 1),
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
                'average' => round($mirroredStats->average_price ?? 0, 2),
                'minimum' => round($mirroredStats->min_price ?? 0, 2),
                'maximum' => round($mirroredStats->max_price ?? 0, 2),
                'current' => round($mirroredCurrent ?? 0, 2),
            ];
        }

        return response()->json([
            'popular' => $popular,
            'stats' => [
                'average' => round($stats->average_price ?? 0, 2),
                'minimum' => round($stats->min_price ?? 0, 2),
                'maximum' => round($stats->max_price ?? 0, 2),
                'current' => round($current ?? 0, 2),
            ],
            'history' => $history,
            'active_info' => $activeInfo,
            'period_info' => $periodInfo,
            'mirrored_stats' => $mirroredStatsData,
            'mirrored_history' => $mirroredHistory->isEmpty() ? null : $mirroredHistory,
        ]);
    }

    public function getLogs(Request $request)
    {
        $limit = (int) $request->input('limit', 10);
        $paginator = MarketSyncLog::orderBy('created_at', 'desc')->paginate($limit);

        return response()->json([
            'data' => collect($paginator->items())->map(function ($log) {
                return [
                    'date' => $log->created_at->format('d.m.Y H:i:s'),
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

    public function getArbitrage()
    {
        $offers = MarketOffer::where('created_at', '>=', now()->subHours(6))->get();
        $byPair = [];

        foreach ($offers as $offer) {
            $from = $offer->target_item_id;
            $to = $offer->item_id;
            $byPair[$from][$to][] = [
                'offer_id' => $offer->offer_id,
                'sender_name' => $offer->sender_name,
                'item_id' => $offer->item_id,
                'item_name' => $offer->item_name,
                'amount' => $offer->amount,
                'target_item_id' => $offer->target_item_id,
                'target_item_name' => $offer->target_item_name,
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

                            for ($y = 1; $y <= $t2['lots_remaining']; $y++) {
                                $neededB = $y * $t2['target_amount'];
                                $x = (int) ceil($neededB / $t1['amount']);
                                if ($x > $t1['lots_remaining']) {
                                    continue;
                                }
                                $profitA = ($y * $t2['amount']) - ($x * $t1['target_amount']);
                                if ($profitA > $bestProfit) {
                                    $bestProfit = $profitA;
                                    $best_x = $x;
                                    $best_y = $y;
                                }
                            }

                            if ($bestProfit > 0 && $best_x >= 1 && $best_y >= 1) {
                                $leftoverB = ($best_x * $t1['amount']) - ($best_y * $t2['target_amount']);
                                $loops[] = [
                                    'type' => '2-step',
                                    'start_resource' => $A,
                                    'start_resource_name' => $t1['target_item_name'],
                                    'steps' => [
                                        [
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

                                        for ($z = 1; $z <= $t3['lots_remaining']; $z++) {
                                            $neededC = $z * $t3['target_amount'];
                                            $y = (int) ceil($neededC / $t2['amount']);
                                            if ($y > $t2['lots_remaining']) {
                                                continue;
                                            }
                                            $neededB = $y * $t2['target_amount'];
                                            $x = (int) ceil($neededB / $t1['amount']);
                                            if ($x > $t1['lots_remaining']) {
                                                continue;
                                            }

                                            $profitA = ($z * $t3['amount']) - ($x * $t1['target_amount']);
                                            if ($profitA > $bestProfit) {
                                                $bestProfit = $profitA;
                                                $best_x = $x;
                                                $best_y = $y;
                                                $best_z = $z;
                                            }
                                        }

                                        if ($bestProfit > 0 && $best_x >= 1 && $best_y >= 1 && $best_z >= 1) {
                                            $leftoverB = ($best_x * $t1['amount']) - ($best_y * $t2['target_amount']);
                                            $leftoverC = ($best_y * $t2['amount']) - ($best_z * $t3['target_amount']);

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
                } // end isset($byPair[$B])
            }
        }

        usort($loops, function ($a, $b) {
            return $b['profit']['amount'] <=> $a['profit']['amount'];
        });

        return response()->json(array_slice($loops, 0, 20));
    }
}
