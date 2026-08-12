<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LogLevel;
use App\Http\Resources\AccountResource;
use App\Http\Resources\BotLogResource;
use App\Models\Account;
use App\Models\BotLog;
use App\Models\ScheduledTask;
use Illuminate\Http\JsonResponse;

/**
 * RESTful entry point for system dashboard statistics.
 */
class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $accounts = Account::withCount('scheduledTasks')->get();
        $logs = BotLog::with('account:id,username,nickname')
            ->latest('created_at')
            ->limit(50)
            ->get();

        $stats = [
            'total_accounts' => $accounts->count(),
            'active_tasks' => ScheduledTask::where('is_active', true)->count(),
            'today_actions' => BotLog::where('created_at', '>=', now()->startOfDay())->count(),
            'errors' => BotLog::where('level', LogLevel::Error)->where('created_at', '>=', now()->startOfDay())->count(),
        ];

        return new JsonResponse([
            'accounts' => AccountResource::collection($accounts),
            'logs' => BotLogResource::collection($logs),
            'stats' => $stats,
            'meta' => [
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}
