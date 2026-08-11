<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TSO Scheduler Mode
    |--------------------------------------------------------------------------
    |
    | Supported modes:
    | - 'queue': Enqueues jobs to dedicated queues ('tso-tasks', 'tso-market').
    |            Requires a persistent queue worker process (e.g. supervisor).
    | - 'cron':  Enqueues jobs to dedicated queues, and if --work is passed,
    |            runs an isolated worker to process 'tso-tasks,tso-market'.
    | - 'sync':  Executes jobs inline/synchronously without external queues.
    |
    */
    'scheduler_mode' => env('TSO_SCHEDULER_MODE', 'queue'),

    /*
    |--------------------------------------------------------------------------
    | TSO Dedicated Queues
    |--------------------------------------------------------------------------
    */
    'queues' => [
        'tasks' => 'tso-tasks',
        'market' => 'tso-market',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stale Task Timeout (Minutes)
    |--------------------------------------------------------------------------
    */
    'stale_task_timeout_minutes' => (int) env('TSO_STALE_TASK_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Task Execution Limits & Budget
    |--------------------------------------------------------------------------
    */
    'tasks' => [
        'max_result_length' => 150,
        'max_action_attempts' => 2,
        'retry_session_errors' => [1005, 1012],
    ],
];
