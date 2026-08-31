<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\AccountResource;
use App\Http\Resources\BotLogResource;
use App\Models\Account;
use App\Models\BotLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller for retrieving system and account bot logs.
 */
class LogController extends Controller
{
    /**
     * Show logs, filterable by account and log level.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->input('per_page', $request->input('limit', 100));
        $perPage = max(1, min(100, $perPage));

        $query = BotLog::with([
            'account' => static function ($query): void {
                $query->select('id', 'username', 'nickname')->withExists('marketServerConnections');
            },
        ])->latest('created_at');

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->input('account_id'));
        }

        if ($request->filled('level')) {
            $query->where('level', $request->input('level'));
        }

        $logs = $query->paginate($perPage);
        $accounts = Account::select('id', 'username', 'nickname')
            ->withExists('marketServerConnections')
            ->orderBy('username')
            ->get();

        return BotLogResource::collection($logs)->additional([
            'accounts' => AccountResource::collection($accounts),
            'meta' => [
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}
