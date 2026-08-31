<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BotLog;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class SystemLogCleanupService
{
    /**
     * Determine if automatic retention log cleanup should be executed.
     */
    public function shouldRunCleanup(Carbon $now): bool
    {
        $retentionDays = (int) Setting::get('log_retention_days', 30);

        if ($retentionDays <= 0) {
            return false;
        }

        $lastCleanupAt = Setting::get('last_log_cleanup_at');

        if (! $lastCleanupAt) {
            return true;
        }

        try {
            $lastRunTime = Carbon::parse($lastCleanupAt);

            return $now->diffInHours($lastRunTime) >= 24;
        } catch (Throwable $e) {
            Log::warning("[LogCleanup] Failed to parse last_log_cleanup_at timestamp: {$e->getMessage()}");

            return true;
        }
    }

    /**
     * Clean expired system logs according to Log Retention Policy.
     */
    public function cleanExpiredLogs(Carbon $now): int
    {
        $retentionDays = (int) Setting::get('log_retention_days', 30);

        if ($retentionDays <= 0) {
            return 0;
        }

        $cutoffDate = $now->copy()->subDays($retentionDays);

        $deletedCount = (int) BotLog::where('created_at', '<', $cutoffDate)->delete();

        Setting::set('last_log_cleanup_at', $now->toIso8601String());

        return $deletedCount;
    }

    /**
     * Clear all system logs (manual trigger).
     */
    public function clearAllLogs(): void
    {
        BotLog::truncate();
    }

    /**
     * Safely process auto-cleanup as part of scheduled cron workflow.
     */
    public function processAutoCleanup(Carbon $now): bool
    {
        if (! $this->shouldRunCleanup($now)) {
            return false;
        }

        try {
            $deleted = $this->cleanExpiredLogs($now);
            Log::info("[LogCleanup] Retention cleanup finished: pruned {$deleted} expired log records");

            return true;
        } catch (Throwable $e) {
            Log::error("[LogCleanup] Retention cleanup failed: {$e->getMessage()}", [
                'exception' => $e,
            ]);

            return false;
        }
    }
}
