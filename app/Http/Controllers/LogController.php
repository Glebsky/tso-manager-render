<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Logs\IndexLogRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\BotLogResource;
use App\Services\Logs\BotLogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Controller for retrieving system and account bot logs via REST and SSE.
 */
class LogController extends Controller
{
    private const int STREAM_DURATION_SECONDS = 25;

    private const int SLEEP_MICROSECONDS = 1_000_000;

    private const int PING_INTERVAL_SECONDS = 5;

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

    /**
     * Stream real-time logs via Server-Sent Events (SSE).
     */
    public function stream(IndexLogRequest $request): StreamedResponse
    {
        $afterId = $request->afterId() ?? $this->logService->latestId();
        $accountId = $request->accountId();
        $level = $request->level();
        $isTesting = app()->environment('testing');
        $maxDuration = $isTesting ? 1 : self::STREAM_DURATION_SECONDS;

        $response = new StreamedResponse(function () use ($afterId, $accountId, $level, $isTesting, $maxDuration) {
            $lastId = $afterId;
            $startTime = time();
            $lastPing = time();

            if (! $isTesting) {
                while (ob_get_level() > 0) {
                    ob_end_flush();
                }
            }

            echo ": connected\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            do {
                if (connection_aborted() !== 0) {
                    break;
                }

                $newLogs = $this->logService->getNewLogsAfter($lastId, $accountId, $level);

                if ($newLogs->isNotEmpty()) {
                    foreach ($newLogs as $log) {
                        $lastId = $log->id;
                        $payload = (new BotLogResource($log))->resolve();
                        echo "id: {$log->id}\n";
                        echo "event: log\n";
                        echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";
                    }
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                if ((time() - $lastPing) >= self::PING_INTERVAL_SECONDS) {
                    echo ": ping\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                    $lastPing = time();
                }

                if ($isTesting) {
                    break;
                }

                usleep(self::SLEEP_MICROSECONDS);
            } while ((time() - $startTime) < $maxDuration);
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
