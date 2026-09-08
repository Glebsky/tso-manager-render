<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\LogLevel;
use App\Http\Resources\AccountResource;
use App\Http\Resources\BotLogResource;
use App\Models\Account;
use App\Models\BotLog;
use App\Models\ScheduledTask;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardOverviewService
{
    /**
     * Build the aggregated dashboard payload.
     *
     * @return array{
     *     accounts: AnonymousResourceCollection,
     *     logs: AnonymousResourceCollection,
     *     stats: array{total_accounts: int, active_tasks: int, today_actions: int, errors: int},
     *     meta: array{server_time: string}
     * }
     */
    public function getOverview(): array
    {
        $accounts = Account::lite()
            ->withExists('marketServerConnections')
            ->withCount('scheduledTasks')
            ->get();

        $logs = BotLog::with(['account' => static function ($query): void {
            $query->select('id', 'username', 'nickname')->withExists('marketServerConnections');
        }])
            ->latest('created_at')
            ->limit(50)
            ->get();

        $todayLogStats = BotLog::where('created_at', '>=', now()->startOfDay())
            ->selectRaw('count(*) as total, count(case when level = ? then 1 end) as errors', [LogLevel::Error->value])
            ->first();

        $stats = [
            'total_accounts' => $accounts->count(),
            'active_tasks' => ScheduledTask::where('is_active', true)->count(),
            'today_actions' => (int) ($todayLogStats->total ?? 0),
            'errors' => (int) ($todayLogStats->errors ?? 0),
        ];

        return [
            'accounts' => AccountResource::collection($accounts),
            'logs' => BotLogResource::collection($logs),
            'stats' => $stats,
            'meta' => [
                'server_time' => now()->toIso8601String(),
            ],
        ];
    }
}
