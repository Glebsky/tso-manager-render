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
        'max_action_attempts' => 3,

        /*
         | Kept for backward compatibility with older call sites.
         */
        'retry_session_errors' => [1005, 1012],

        /*
         | Codes that mean "our web login is dead" — a full re-login is required.
         */
        'relogin_errors' => [1005],

        /*
         | Codes that mean "this AMF endpoint is bound to another/newer session".
         | Re-logging in would only create yet another session, so we just drop
         | the cached transport for the account and retry.
         */
        'transport_retry_errors' => [1012],
        'transport_retry_delay' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Network & Transport Security
    |--------------------------------------------------------------------------
    |
    */
    'ssl_verify' => (bool) env('TSO_SSL_VERIFY', true),
    'http_timeout' => (int) env('TSO_HTTP_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | AMF Transport Session TTL (Seconds)
    |--------------------------------------------------------------------------
    |
    | How long one resolved game session (endpoint URL + DSId) is reused, by
    | every process, before a new one is created. Each `/authenticate` + `Z…`
    | round trip creates a NEW game session and supersedes the previous one, so
    | resolving per job is exactly what produces error 1012. Keep this high
    | enough that consecutive jobs share a single session; a re-login
    | invalidates the record automatically because it is bound to the auth token.
    |
    */
    'session_ttl_seconds' => (int) env('TSO_SESSION_TTL', 300),

    /*
    | How long a job waits for another process to finish talking to the game
    | server on behalf of the same account. Two processes resolving at the same
    | time would each create a session and invalidate the other's.
    */
    'session_lock_wait_seconds' => (int) env('TSO_SESSION_LOCK_WAIT', 20),

    /*
    |--------------------------------------------------------------------------
    | Collectibles (Allow-list for Building Clicks)
    |--------------------------------------------------------------------------
    |
    | WARNING: This list authorizes sending the destructive DESTRUCT_BUILDING (65)
    | command. Any erroneous entry here risks permanently tearing down a player's building.
    |
    */
    'collectibles' => [
        'clickable_patterns' => [
            ['pattern' => '/^Collectible.+Building$/i', 'kind' => 0],
            ['pattern' => '/^StarfallStarDust.*$/i',    'kind' => 1],
        ],
    ],
];
