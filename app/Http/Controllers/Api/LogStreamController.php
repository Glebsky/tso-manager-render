<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BotLogResource;
use App\Models\BotLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogStreamController extends Controller
{
    /**
     * Maximum duration of a single SSE connection in seconds.
     * Keeps workers from hanging indefinitely while letting EventSource reconnect cleanly.
     */
    private const int STREAM_DURATION_SECONDS = 25;

    /**
     * Delay in microseconds between polling cycles inside stream.
     */
    private const int SLEEP_MICROSECONDS = 1_000_000;

    /**
     * Heartbeat ping interval in seconds.
     */
    private const int PING_INTERVAL_SECONDS = 5;

    public function stream(Request $request): StreamedResponse
    {
        $lastEventId = $request->header('Last-Event-ID');
        $afterId = $request->filled('after_id')
            ? (int) $request->input('after_id')
            : ($lastEventId !== null && is_numeric($lastEventId) ? (int) $lastEventId : null);

        // If no after_id provided, start from the latest existing log ID so we only stream new ones
        if ($afterId === null) {
            $latestId = BotLog::max('id');
            $currentId = $latestId !== null ? (int) $latestId : 0;
        } else {
            $currentId = $afterId;
        }

        $accountId = $request->filled('account_id') ? (int) $request->input('account_id') : null;
        $level = $request->filled('level') ? (string) $request->input('level') : null;
        $isTesting = app()->environment('testing');
        $maxDuration = $isTesting ? 1 : self::STREAM_DURATION_SECONDS;

        $response = new StreamedResponse(function () use ($currentId, $accountId, $level, $isTesting, $maxDuration) {
            $lastId = $currentId;
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

                $query = BotLog::with([
                    'account' => static function ($query): void {
                        $query->select('id', 'username', 'nickname')->withExists('marketServerConnections');
                    },
                ])
                    ->where('id', '>', $lastId)
                    ->orderBy('id')
                    ->limit(50);

                if ($accountId !== null) {
                    $query->where('account_id', $accountId);
                }

                if ($level !== null) {
                    $query->where('level', $level);
                }

                $newLogs = $query->get();

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
