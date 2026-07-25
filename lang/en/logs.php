<?php

declare(strict_types=1);

/*
 * Activity log messages (BotLog / MarketSyncLog).
 *
 * These strings are shown to users on the "Activity Logs" screen and in the
 * Market Analytics sync log, so keep them short, human-readable and free of
 * internal jargon.
 */

return [
    'account' => [
        'sync_success' => 'Account synced: :buildings buildings, :resources resources, :specialists specialists, :buffs buffs.',
        'sync_failed' => 'Account sync failed: :error',
        'sync_job_failed' => 'Background account sync failed after all retry attempts: :error',
        'action_success' => 'Action ":type" completed successfully.',
        'action_failed' => 'Action ":type" failed: :error',
        'session_updated' => 'Session tokens updated manually.',
    ],
    'task' => [
        'scheduled' => 'Task #:id ":type" created and scheduled (schedule: :schedule).',
        'updated' => 'Task #:id ":type" updated.',
        'enabled' => 'Task #:id ":type" enabled.',
        'disabled' => 'Task #:id ":type" disabled.',
        'deleted' => 'Task #:id ":type" deleted.',
        'completed' => 'Task ":type" completed successfully. Result: :result',
        'completed_with_errors' => 'Task ":type" finished with errors. Result: :result',
        'failed' => 'Task ":type" failed: :error',
        'step_completed' => 'Task #:id: step :step (:type) completed successfully.',
        'step_failed' => 'Task #:id: step :step (:type) failed: :error',
        'job_failed' => 'Task #:id ":type" failed after all retry attempts: :error',
    ],
    'market' => [
        'sync_started' => 'Market sync started for server [:server].',
        'fetch_attempt' => 'Fetching market offers (attempt :attempt of :max)...',
        'zone_loading_retry' => 'Game server is still loading the zone (error 1012). Retrying in :delay s...',
        'session_expired_retry' => 'Game session expired (error :code). Re-authenticating and retrying...',
        'attempt_failed' => 'Attempt :attempt of :max failed: :error',
        'sync_success' => 'Market sync finished: :count offers received from server [:server].',
        'sync_failed' => 'Market sync failed: :error',
        'sync_job_failed' => 'Background market sync failed after all retry attempts: :error',
    ],
];
