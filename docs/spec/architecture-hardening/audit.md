# Architecture Audit — Evidence

Every finding below references real files in the repository. Line counts were
measured with `wc -l` over `app/`, `resources/`, `routes/`, `config/`,
`database/` (26 984 lines total, `vendor/` and `node_modules/` excluded per
`AGENTS.md` §5).

---

## A. Security & isolation (highest priority)

### A1. TLS private key committed — `docker/nginx/certs/privkey.pem`
`docker/nginx/certs/` contains `fullchain.pem` (1 594 B) and `privkey.pem`
(1 704 B). `.gitignore` covers `.env` and `/storage/*.key` but not `docker/**/certs`.
Violates constitution §9 (secrets are never repository content) and §14.

**Action:** rotate/reissue the certificate, remove the files from the working tree
and history, add `docker/nginx/certs/*.pem` to `.gitignore`, mount certificates as
a Docker volume or generate them via the entrypoint, document the required host
paths in `README.md`.

### A2. No ownership model — accounts/tasks/market servers are global
- `app/Providers/AuthServiceProvider.php`: `$policies = []`, empty `boot()`.
- No `authorize()`/`Gate::` call anywhere in `app/Http/Controllers/**` (grep: only
  Form Request `authorize()` stubs).
- `accounts`, `scheduled_tasks`, `market_server_connections` migrations have no
  `user_id` column (grep `user_id` matches only `create_sessions_table`).
- `AccountController::index()` returns `Account::latest()->get()` — every
  authenticated user sees and mutates every account.

Violates constitution §6 («every protected mutation verifies ownership»,
«route-model binding is not authorization») and §15 (cross-account denial tests).

### A3. Credentials stored in plaintext — `app/Models/Account.php`
`$fillable` includes `password`, `dso_auth_user`, `dso_auth_token`; `$casts`
contains only `last_sync_at`. No `encrypted` cast anywhere in `app/Models`
(grep `encrypted` → 0 matches). `$hidden` hides the field from JSON but not from
the database, logs or dumps. Violates constitution §9.

### A4. Internal exception text returned to clients
`AccountController::sync()` catches `Exception` and returns
`'Sync failed: '.$e->getMessage()` with HTTP 500. `app/Exceptions/Handler.php` is
the stock stub (`reportable(fn () => null)`), so nothing maps domain exceptions to
a safe envelope centrally, even though 11 domain exceptions exist in
`app/Exceptions/`. Violates constitution §9 and §10.

---

## B. Backend design (SOLID / DRY)

### B1. `app/Services/TaskExecutionService.php` — 547 lines, SRP + DRY
The class simultaneously owns: entry-point guards, session (re)authentication,
retry policy, sequence orchestration, single-action orchestration, task state
persistence, result-string formatting/truncation, `BotLog` writing and diagnostic
logging.

Concrete duplication — the same «finalize the task» block is written **four
times** (`executeSequenceTask`, `executeSingleTask` success, `executeSingleTask`
failure, `handleOverallFailure`, plus `finalizeSequence`):

```php
$updateData = ['status' => ..., 'last_run_at' => now(),
    'last_result' => 'OK: '.(strlen($result) > 100 ? substr($result, 0, 97).'...' : $result),
    'payload' => $payload, 'completed_steps' => 0, 'execution_token' => null];
if ($task->schedule_type === 'once') { $updateData['is_active'] = false; }
$task->update($updateData);
BotLog::create([...]);
```

The truncation limit differs between copies (100 vs 150 vs 97/147) — proof that
the copies have already drifted.

Two competing engines for one workflow: `executeSequenceTask()` runs all steps
in-process with `sleep($delay)`, while `executeSequenceStep()` +
`ExecuteScheduledTaskJob` run the same steps under a time budget with delayed
hand-off. Both write task state with slightly different rules (the step-based one
adds `PARTIAL: `, the in-process one does not). This is the root cause of the two
open bugs in `docs/foundbugs.md` («ручной запуск tasks странно работает»,
«"выполнено" висит долго»).

Other violations in the same file:
- `mixed $account` in `executeSequenceTask`, `executeSingleTask`,
  `executeSingleActionWithRetry`, `executeSingleAction` — the collaborator
  interface `TaskActionHandlerInterface::handle()` is already typed
  `Account $account`, so the weaker type is pure type erosion.
- `throw new Exception("Task #{$task->id} is not a sequence task.")` — generic
  exception plus hardcoded English, while 11 domain exceptions already exist.
- `catch (Throwable $e) { /* Ignore parse failures on non-AMF mock strings in tests */ }`
  in `executeSingleAction()` — production error suppression driven by test data
  (constitution §13: never catch `Throwable` to suppress).
- `@unlink($this->authService->getCookieFile($account))` — the caller reaches into
  another service's storage layout and suppresses the error (Law of Demeter,
  encapsulation, §13).
- Retry constants inline (`[1005, 1012]`, `sleep(2)`, `maxAttempts = 2`) instead of
  `config/game.php` (constitution §7: no scattered literals).

### B2. Magic strings instead of enums (PHP 8.1 backed enums are available)
Statuses (`pending`, `queued`, `running`, `completed`, `failed`), task types
(`sequence`, `start_production`, …), `schedule_type` (`once`), log levels
(`success`, `error`), result prefixes (`OK: `, `ERROR: `, `PARTIAL: `,
`SKIPPED: `) are string literals spread across `TaskExecutionService`,
`ScheduledTaskService`, `ExecuteScheduledTaskJob`, `TaskSchedulerEngine`,
`ScheduledTaskResource`, Form Requests and the Vue views (31+ literal matches in
`app/` alone). `app/` has no `Enums/` directory. Violates constitution §13 («no
magic protocol values») and makes exhaustiveness unverifiable by PHPStan.

### B3. API envelope is not owned by one place
`app/Support/Http/ApiResponder.php` exists and documents itself as «the single
place that owns the JSON envelope», yet:
- only 11 files reference it;
- 22 hand-built `response()->json([...])` calls remain — `AccountController` (10),
  `ScheduledTaskController` (7), `SettingsController` (3), `DashboardController`
  (1), `AuthController` (1);
- those responses hardcode English UI text (`'Account added.'`,
  `'Task scheduled.'`, `'Task deleted.'`, `'Task queued for background
  execution.'`) while the rest of the codebase already uses `__('logs.*')` /
  `__('tasks.*')`. `docs/foundbugs.md` marks «Убрать русский язык из кода,
  переделать на языковые файлы» as done — this is the remaining English residue.
- `ScheduledTaskController` repeats `'server_time' => now()->toIso8601String()`
  in four actions (DRY), a concern that belongs to the resource or a middleware.

### B4. Static-analysis gate is decorative — `phpstan.neon`
```neon
level: 0
paths: [app/Services/MarketCacheService.php, app/Http/Middleware/HttpCacheHeaders.php, app/Models/Setting.php]
ignoreErrors: ['#Call to an undefined static method App\Models\...#']
```
Three of ~150 PHP files are analysed at the weakest level, and the ignore pattern
suppresses the whole class of Eloquent static-call errors. `Makefile`,
`AGENTS.md` §3 and `docs/spec/solid-refactor/verification.md` all advertise
«PHPStan: 0 errors» — the report is technically true and practically meaningless.

### B5. Fat model with hidden query cost — `app/Models/Account.php`
- Missing `declare(strict_types=1);` (constitution §13) — one of the few files that
  still lacks it.
- `getAvatarIdAttribute`, `getBuildingCountAttribute`, `getServerNameAttribute`
  each `json_decode($this->zone_data, true)` independently, inside
  `try { } catch (\Throwable) { return null; }` — three decodes of the same blob
  per row, with silent failure.
- `zone_data` has no `array` cast, so every consumer re-implements
  `is_array($raw) ? $raw : json_decode($raw, true)` — the same expression is
  duplicated in `AccountController::zone()`.
- `getIsMarketConnectedAttribute()` executes
  `MarketServerConnection::where('account_id', $this->id)->exists()` and is listed
  in `$appends`, i.e. one extra query per account on **every** serialization —
  a textbook N+1 on `GET /api/accounts` and `GET /api/dashboard`
  (constitution §8: «avoid N+1 queries»).

### B6. Unbounded collections
`AccountController::index()` — `Account::latest()->get()`;
`ScheduledTaskService::tasks()` — `ScheduledTask::with(...)->orderBy('id','desc')->get()`;
`ScheduledTaskService::accounts()` — `Account::orderBy('username')->get()`.
No pagination or limit anywhere. Violates constitution §8 («paginate or stream
unbounded datasets») and §10 («bounded pagination»).

### B7. `app/Services/TsoAmfService.php` — 525 lines, PSR-4 and DIP violations
The file declares four extra classes next to the service
(`defaultGame_Communication_VO_dServerCall`,
`defaultGame_Communication_VO_dServerAction`,
`flex_messaging_messages_RemotingMessage`, …) — non-PSR-4, non-autoloadable,
all properties untyped (`public $data;`). The service mixes protocol message
construction, AMF encoding delegation, HTTP transport and logging, and is
injected as a concrete class (no `TsoClient` contract), so protocol code cannot
be faked in tests without hitting the real transport (constitution §7 last rule,
§5 «add an interface at a real replaceable boundary»).
`TsoAuthService` (425 lines) has the same shape: cURL calls, cookie-file layout,
Ubisoft OAuth URLs and token parsing in one class.

---

## C. Frontend

### C1. Route-level god components
`resources/js/views/Tasks.vue` 2 691 lines, `MarketAnalytics.vue` 1 769,
`AccountDetail.vue` 1 544, `PublicMarketAnalytics.vue` 1 329 — 7 333 lines, 27 %
of the whole codebase, in four files. Only 6 shared components exist
(`AccountCard`, `BuildingRow`, `LanguageSwitcher`, `LoadingOverlay`, `LogEntry`,
`Spinner`) plus 3 market components.

### C2. `Tasks.vue` uses the Options API
`grep '<script'` → `978:<script>`; there is no `<script setup>` block in the
largest component of the app. Violates constitution §12 («use `<script setup>`
for new/edited components»).

### C3. No API boundary, no composables
`axios.` is called directly from views: `Tasks.vue` 9, `MarketAnalytics.vue` 9,
`AccountDetail.vue` 4, `Settings.vue` 4, `Accounts.vue` 2, plus components
(`AccountCard.vue` 2, `BuildingRow.vue` 1). There is no `resources/js/composables/`
directory and no per-domain API client — `resources/js/services/` holds only
`apiCacheService.js` (514 lines) and `gameImageService.js`. Violates
constitution §12 («API calls belong in service/composable boundaries») and §5.

### C4. Duplicated analytics screens
`MarketAnalytics.vue` (1 769) and `PublicMarketAnalytics.vue` (1 329) implement the
same charts, filters, tables, period handling and loading states against
protected vs public endpoints. ~3 100 lines that should be one set of components
plus two thin route wrappers differing only by data source and exposed fields.

---

## D. Dead code & hygiene

### D1. Legacy Blade UI is unreachable
`routes/web.php` renders only `view('app')` and the auth screens
(`AuthController`). Grepping `view('<name>')` for `accounts.index`,
`accounts.show`, `logs.index`, `settings.index`, `tasks.index`, `dashboard`,
`welcome`, `layouts.app` returns **0 references** in `app/` and `routes/`.
That is ~1 500 lines of Blade (plus `resources/views/partials/*`) duplicating the
SPA — the exact «убрать неиспользуемый код» item still open in
`docs/foundbugs.md`.

### D2. Repository hygiene
- `.phpunit.result.cache` is present in the tree although listed in `.gitignore`.
- `Makefile: install` uses `@if not exist .env copy .env.docker .env` — Windows
  `cmd` syntax; the target fails on Linux/macOS, while every other target is
  POSIX Docker. The docker item is still unchecked in `docs/foundbugs.md`.
- `docs/spec/solid-refactor/README.md` links documents through
  `file:///C:/OSPanel/domains/...` absolute local paths — broken for everyone else;
  should be relative links.

---

## E. Test coverage gaps (`tests/` = 28 files)

Existing: 14 Feature + 11 Unit tests. Missing relative to constitution §15:
- no cross-user / cross-account denial test (nothing to test until A2 lands);
- no test asserting the API envelope contract per endpoint;
- no idempotency/duplicate-delivery test for `ExecuteScheduledTaskJob` (only
  `SchedulerArchitectureTest` exists);
- no regression test for the two open task-execution bugs in `docs/foundbugs.md`;
- no frontend test harness at all (`package.json` has no test script), so §15's
  frontend clause is satisfied only by `npm run build`.
