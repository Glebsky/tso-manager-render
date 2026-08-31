<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Logs\IndexLogRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\BotLogResource;
use App\Services\Logs\BotLogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Controller for retrieving system and account bot logs.
 */
class LogController extends Controller
{
    public function __construct(
        private readonly BotLogService $logService,
    ) {}

    /**
     * Show logs, filterable by account and log level.
     */
    public function index(IndexLogRequest $request): AnonymousResourceCollection
    {
        $logs = $this->logService->paginate(
            $request->perPage(),
            $request->accountId(),
            $request->level()
        );

        return BotLogResource::collection($logs)->additional([
            'accounts' => AccountResource::collection($this->logService->getFilterAccounts()),
            'meta' => [
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }
}
