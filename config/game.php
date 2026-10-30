<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TSO Scheduler Mode
    |--------------------------------------------------------------------------
    |
    | Supported modes:
    | - 'queue': Enqueues jobs to the queues below. Requires a persistent
    |            queue worker process (Docker `worker` service, supervisor).
    | - 'cron':  Enqueues jobs to the queues below and then runs an inline
    |            worker (`--stop-when-empty`) over the same queues on every
    |            scheduler tick. Intended for OSPanel / Windows Task Scheduler.
    | - 'sync':  Executes jobs inline/synchronously without external queues.
    |
    */
    'scheduler_mode' => env('TSO_SCHEDULER_MODE', 'queue'),

    /*
    |--------------------------------------------------------------------------
    | TSO Dedicated Queues
    |--------------------------------------------------------------------------
    |
    | Single source of queue names for jobs and for every worker. The order
    | is the worker priority order. Keep the TSO_WORKER_QUEUES default in
    | docker-compose.yml in sync (enforced by QueueTopologyTest).
    |
    */
    'queues' => [
        'tasks' => 'tso-tasks',
        'accounts' => 'tso-accounts',
        'market' => 'tso-market',
        'default' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Worker Queues Override
    |--------------------------------------------------------------------------
    |
    | Optional comma-separated list of queues for worker instances to consume.
    | When omitted, workers fall back to all declared game.queues.
    |
    */
    'worker_queues' => env('TSO_WORKER_QUEUES'),

    /*
    |--------------------------------------------------------------------------
    | Feature Toggles for Schedulers
    |--------------------------------------------------------------------------
    */
    'market_sync_enabled' => env('MARKET_SYNC_ENABLED', true),
    'account_sync_enabled' => env('ACCOUNT_SYNC_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Stale Task Timeout (Minutes)
    |--------------------------------------------------------------------------
    */
    'stale_task_timeout_minutes' => (int) env('TSO_STALE_TASK_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Scheduler
    |--------------------------------------------------------------------------
    |
    | heartbeat_interval_seconds: how often a running task run refreshes its
    | `heartbeat_at` while waiting (e.g. between sequence steps). Must stay
    | well below stale_task_timeout_minutes so a healthy wait is never
    | mistaken for a stuck run (FR-007).
    |
    | missed_grace_minutes: a due slot is still executed if the tick arrives
    | within this many minutes after `next_run_at`. Later than that the slot
    | is skipped and the task moves to its next future slot (ADR-002).
    |
    */
    'scheduler' => [
        'heartbeat_interval_seconds' => (int) env('TSO_HEARTBEAT_INTERVAL', 15),
        'missed_grace_minutes' => (int) env('TSO_MISSED_GRACE_MINUTES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Task Execution Limits & Budget
    |--------------------------------------------------------------------------
    */
    'tasks' => [
        'max_result_length' => (int) env('TSO_MAX_TASK_RESULT_LENGTH', 1000),
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
         | Codes that mean "our game session was superseded by a newer one".
         | GameSessionGuard takes the session over like a freshly started client
         | (new client identity + new load-server session on the same token,
         | confirmed home-zone entry). If the fresh session is rejected as well,
         | the single forced login of the call is used.
         */
        'transport_retry_errors' => [1012],

        /*
         | Codes that mean "the server is initializing or loading the player's zone".
         | 1001 (SERVER_ZONE_INIT_FAILED) is a transient state while the zone server
         | spins up the island instance. GameSessionGuard retries with backoff and
         | session identity renewal before failing.
         */
        'zone_init_errors' => [1001],
        'zone_init_retry_delay' => (int) env('TSO_ZONE_INIT_RETRY_DELAY', 3),

        /*
         | Seconds to wait before retrying an action whose per-account session
         | lock was busy (SessionBusyException). No re-login is performed.
         */
        'session_busy_retry_delay' => (int) env('TSO_SESSION_BUSY_RETRY_DELAY', 5),
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
    | The TTL is sliding: every successful AMF command refreshes it, so an
    | actively used session never expires. The value only limits how long an
    | idle session is kept before a fresh one is resolved.
    |
    */
    'session_ttl_seconds' => (int) env('TSO_SESSION_TTL', 1800),

    /*
    |--------------------------------------------------------------------------
    | Home Zone Cache
    |--------------------------------------------------------------------------
    |
    | How long the persisted home zone (accounts.zone_data) is reused by
    | buffs and other readers before it is read from the game again. Every
    | live read is ~0.5-1.5 MB that gets written back to the database, so
    | this window is the main lever on outbound traffic. Account sync always
    | refreshes it; home-zone invalidation (session recovery, manual buff
    | step) makes the next reader fetch live.
    |
    */
    'home_zone_cache_ttl_seconds' => (int) env('TSO_HOME_ZONE_CACHE_TTL', 600),

    /*
    | How long a job waits for another process to finish talking to the game
    | server on behalf of the same account. Two processes resolving at the same
    | time would each create a session and invalidate the other's.
    */
    'session_lock_wait_seconds' => (int) env('TSO_SESSION_LOCK_WAIT', 20),

    /*
    | How long the per-account lock (AMF command / login) may be held before
    | the cache store expires it. Must cover the worst operation done under
    | the lock — session resolve (authenticate + Z) plus the command itself,
    | each bounded by http_timeout — otherwise the lock evaporates mid-flight
    | and a second process starts a competing session (FR-008, INV-004).
    | If the lock is not acquired in time the command is NOT sent; a
    | SessionBusyException is thrown and the action is retried later.
    */
    'session_lock_ttl_seconds' => (int) env('TSO_SESSION_LOCK_TTL', ((int) env('TSO_HTTP_TIMEOUT', 30)) * 4 + 10),

    /*
    |--------------------------------------------------------------------------
    | GetFriends Version Hash
    |--------------------------------------------------------------------------
    |
    | SHA1-хеш, который клиент (Flash SWF) передаёт серверу в запросе
    | GetFriends (dGetFriendsVO::version). Обновляется при каждом патче игры.
    | Актуальное значение берётся из перехваченного AMF-трафика живого клиента.
    |
    */
    'friends_version' => env('TSO_FRIENDS_VERSION', '3c2374c967d4223b5ccb643c0305c8d9eeb0a943'),
    'zone_version_hash' => env('TSO_ZONE_VERSION_HASH', '799f95cad1c9a5acfa981dee11d3eb73'),

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

    /*
    |--------------------------------------------------------------------------
    | Buildings
    |--------------------------------------------------------------------------
    |
    | WARNING. `number` — это игровой номер здания, который уходит в команду 50.
    | Ошибка в номере = построено не то здание и безвозвратно потрачены ресурсы
    | игрока. Источник номеров: docs/references/icons.xml (атрибут id).
    | Источник пар «руда -> шахта»: docs/references/globals.xml
    | (атрибут restrictPlacingToDeposit).
    | Процедура сверки описана в docs/spec/build-mine/data-sources.md.
    |
    */
    'buildings' => [
        'mines' => [
            'BronzeOre' => ['mine' => 'BronzeMine', 'number' => 36, 'max_level' => 7],
            'Coal' => ['mine' => 'CoalMine', 'number' => 37, 'max_level' => 7],
            'GoldOre' => ['mine' => 'GoldMine', 'number' => 46, 'max_level' => 7],
            'IronOre' => ['mine' => 'IronMine', 'number' => 50, 'max_level' => 7],
            'Salpeter' => ['mine' => 'SalpeterMine', 'number' => 63, 'max_level' => 7],
            'TitaniumOre' => ['mine' => 'TitaniumMine', 'number' => 69, 'max_level' => 7],
        ],
    ],
];
