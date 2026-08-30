# Architecture Hardening — Design

## 1. Target layering (unchanged direction, enforced)

```text
HTTP (Controllers -> Form Requests -> Policies -> JsonResources -> ApiResponder)
Console Commands / Queue Jobs
        ↓
Application services (orchestrators, ≤ 150 lines each)
        ↓
Domain collaborators (writers, summarizers, policies, value objects, enums)
        ↓
Adapters (Eloquent models, TsoClientInterface, CacheRepository, Logger)

Vue routes -> composables -> per-domain API clients -> apiCacheService -> JSON API
```

Rules enforced by this phase: no service reaches into another service's storage
layout; no controller builds a response envelope by hand; no view calls axios; no
string literal encodes a domain state.

---

## 2. Task execution redesign (core of the phase)

### 2.1 Current shape
```text
TaskExecutionService (547 lines)
 ├─ execute()                  guards + auth + branch
 ├─ executeSequenceTask()      in-process loop, sleep(), finalization copy #1
 ├─ executeSingleTask()        finalization copies #2 (ok) and #3 (fail)
 ├─ handleOverallFailure()     finalization copy #4
 ├─ executeSequenceStep()      job-driven engine
 ├─ finalizeSequence()         finalization copy #5 (adds PARTIAL:)
 ├─ executeSingleActionWithRetry()  retry policy + cookie file unlink
 └─ executeSingleAction()      handler dispatch + AMF error parsing + Throwable swallow
```

### 2.2 Target shape
```text
App\Services\Tasks\
 ├─ TaskRunner.php                  orchestrator: guards -> session -> engine  (≤ 120 lines)
 ├─ Execution\
 │   ├─ TaskExecutionGuard.php      is_active / force / token / account checks -> throws domain ex.
 │   ├─ SequenceStepExecutor.php    executes exactly one step, returns StepOutcome
 │   ├─ SingleActionExecutor.php    executes a non-sequence task, returns StepOutcome
 │   ├─ ActionRetryPolicy.php       codes/attempts/backoff from config/game.php
 │   └─ GameResponseValidator.php   parses AMF result, raises GameServerErrorException
 ├─ State\
 │   ├─ TaskStateWriter.php         THE ONLY writer of status/last_run_at/last_result/
 │   │                              payload/completed_steps/execution_token/is_active
 │   └─ StepResultCollection.php    typed wrapper over payload['step_results']
 └─ Result\
     ├─ StepOutcome.php             VO: completed|failed + error payload
     └─ TaskResultSummary.php       builds and truncates last_result; owns the limit
```

- `TaskStateWriter::finish(ScheduledTask $task, TaskStatus $status, TaskResultSummary $summary): void`
  contains the `schedule_type === once -> is_active = false` rule **once**.
- `TaskActivityLogger` (already exists) becomes the only writer of `BotLog` for
  task events; `TaskRunner` no longer calls `BotLog::create`.
- `ExecuteScheduledTaskJob` keeps only queue metadata, the time budget and the
  hand-off decision; the budget constants move to `config/game.php`.
- Sequence execution exists once: `TaskRunner::runNextStep()`. `execute()` becomes
  a thin «queue it and report» path.

### 2.3 Why this fixes the open bugs
The two unresolved items in `docs/foundbugs.md` (manual run «hangs on выполнено»,
steps not finishing) are consistent with two engines writing overlapping state with
different rules. One writer + one engine + one token/heartbeat rule makes the
failure reproducible in a test instead of a race.

---

## 3. Enums

```php
// app/Enums/TaskStatus.php
enum TaskStatus: string {
    case Pending = 'pending';
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isBusy(): bool { return in_array($this, [self::Queued, self::Running], true); }
}
```
Also `TaskType`, `ScheduleType` (`once`, `interval`, `daily`, … — taken verbatim
from the migrations), `LogLevel` (`success`, `error`, `info`, `warning`),
`TaskResultPrefix` (`OK: `, `ERROR: `, `PARTIAL: `, `SKIPPED: `).

Wired through `ScheduledTask::$casts` and `BotLog::$casts`. Stored strings are
unchanged, so the API contract and existing rows stay valid. `ScheduledTaskService::BUSY_STATUSES`
is replaced by `TaskStatus::isBusy()`, so the notion of «busy» lives with the type
(tell-don't-ask, OCP for new statuses).

---

## 4. Authorization — SUPERSEDED by ADR-001

> The section below described a full ownership layer. It is **not** being built:
> the app is single-operator and registration is already first-user-only, so the
> layer would add migrations, policies and indirection while removing no real risk.
> What ships instead is the tested invariant described in `decisions.md` (ADR-001)
> and FR-1.2. The original design is kept only as the reversal path in case a second
> operator is ever needed.

<details><summary>Original (not to be implemented)</summary>

### Ownership layer

```text
users 1──* accounts 1──* scheduled_tasks
                    1──* market_server_connections
                    1──* bot_logs
```
- Migration A (additive): `user_id` nullable + FK + index on the three owning tables.
- Backfill command: assign existing rows to the single existing operator account
  (idempotent, logged, dry-run flag).
- Migration B: set `nullable(false)` once backfilled.
- `AccountPolicy`, `ScheduledTaskPolicy`, `MarketServerConnectionPolicy` registered
  in `AuthServiceProvider::$policies`; `view/update/delete/execute/sync` abilities.
- Query scoping via `Account::scopeOwnedBy(User $user)` used by every listing, so
  authorization is not «fetch then check».
- Public market endpoints stay unauthenticated but keep serializing only the public
  field set through `PublicServerResource` / `MarketOfferResource`.

</details>

### What actually ships

```text
User (exactly one, enforced at registration)
  └─ owns everything implicitly — no user_id, no policies
Guest
  └─ public market endpoints only, reduced field set
```

- `AuthController::register()` keeps the `lockForUpdate()` first-user check — it is the
  entire authorization model, so it gets explicit tests rather than a comment.
- A route-driven Feature test iterates `Route::getRoutes()` and asserts every route behind
  `auth:sanctum` answers 401 to a guest; new routes are covered without touching the test.
- Public endpoints are pinned by exact-JSON-key assertions so a future field addition
  cannot silently widen the public surface.
- Constitution §6 is reworded: «account isolation» = game accounts, not app users.

### Runtime upgrade (ADR-002)

```text
WP-U1  PHP 8.1 → 8.3           composer.json, Dockerfile, php.ini, deprecations
WP-U2  Laravel 10 → 11         bootstrap/app.php replaces Http/Console Kernel,
                               5 providers → bootstrap/providers.php,
                               11 middleware → application builder (order matters:
                               SetLocale before controllers), Sanctum 3 → 4,
                               schedule() → routes/console.php
WP-U3  → latest Laravel + PHP 8.4
```
Each step is its own branch with all four gates green. The 98-test suite is the safety
net, which is why WP-5 (real static analysis) lands immediately afterwards — the
`larastan` version depends on the Laravel line, so it cannot come first.

---

## 5. Error contract and response shape (ADR-003)

The response contract is `JsonResource` + HTTP status codes; `ApiResponder` and the
`{success, message}` envelope are retired. Rationale and the full status-code table are
in `decisions.md` (ADR-003). In short: `success` restates the status code, `message` is
presentation text that belongs to the SPA's existing i18n bundle, and the envelope is
exactly why English strings leaked into PHP controllers. `meta` on the collection
replaces the four duplicated `server_time` lines.

```php
// app/Exceptions/Handler.php
$this->renderable(function (DomainException $e, Request $request) {
    if (! $request->expectsJson()) { return null; }
    return $this->responder->failure($e->userMessage(), $e->context(), $e->httpStatus());
});
```
A thin marker interface `App\Exceptions\Contracts\HasApiPresentation`
(`userMessage(): string`, `httpStatus(): int`, `context(): array`) is implemented by
the existing 11 exceptions. Adding a new exception requires no Handler edit (OCP).
Non-domain throwables produce a generic localized 500 and a redacted log entry.

---

## 6. Model / query design

```php
final class ZoneSnapshot          // app/Support/Zone/ZoneSnapshot.php
{
    public static function fromArray(?array $data): self;
    public function avatarId(): ?int;
    public function buildingCount(): ?int;
    public function serverName(): ?string;
}
```
`Account` casts `zone_data` to `array`, memoizes one `ZoneSnapshot`, and exposes
`snapshot(): ZoneSnapshot`. Accessors delegate to it — one decode per instance,
no `try/catch` swallow (invalid JSON returns an empty snapshot explicitly).

`is_market_connected` is removed from `$appends`; `AccountResource` reads
`$this->market_server_connections_exists`, populated by
`Account::query()->withExists('marketServerConnections')` in the listing services.
Adds `marketServerConnections(): HasMany` to the model.

Pagination: `per_page` validated by a shared `PaginatedListRequest`
(`integer|min:1|max:config('app.pagination.max')`).

---

## 7. Protocol boundary

```text
app/Services/Amf/Vo/ServerCall.php, ServerAction.php, RemotingMessage.php  (typed, PSR-4)
app/Services/Tso/Contracts/TsoClientInterface.php    send(Account, ServerCall): string
app/Services/Tso/HttpTsoClient.php                   cURL transport + timeouts from config
app/Services/Tso/Contracts/TsoSessionInterface.php   isAuthenticated/login/resetSession
```
`TsoAmfService` keeps only message construction and delegates transport, so tests
fake `TsoClientInterface` (constitution §7: never call the live game in tests).
Binding lives in `TsoServiceProvider`. Encoded-payload equivalence is proven by a
fixture snapshot test written **before** the move.

---

## 8. Frontend structure

```text
resources/js/
 ├─ services/api/{accounts,tasks,market,settings,logs}.js   thin, typed request functions
 ├─ composables/useAsyncResource.js   loading/empty/error/stale-race primitive
 ├─ composables/{useAccounts,useTasks,useTaskForm,useMarketAnalytics,useSyncPolling}.js
 ├─ components/tasks/{TaskList,TaskCard,ActionSequenceBuilder,ActionRow,
 │                    BuildingPicker,SpecialistPicker,BuffPicker,SchedulePicker}.vue
 ├─ components/market/{MarketFilters,MarketPeriodPicker,MarketOffersTable,
 │                     PopularItemsPanel,ArbitragePanel}.vue
 └─ views/  route wrappers only: layout + composable wiring (target ≤ 400 lines)
```
`useAsyncResource` centralizes the stale-response guard (request id compare) that
is currently re-implemented ad hoc in each view — the direct cause of the «data
jumps / spinner sticks» class of issues. `MarketAnalytics.vue` and
`PublicMarketAnalytics.vue` both mount the same components and differ only in the
injected API client.

---

## 9. SOLID mapping (traceability)

| Principle | Violation found | Resolution |
| :--- | :--- | :--- |
| SRP | `TaskExecutionService` = guards+auth+retry+orchestration+persistence+formatting+logging; `Account` model = persistence+JSON parsing+cross-table query | §2 split; §6 `ZoneSnapshot` + relation |
| OCP | `if ($task->task_type === 'sequence')` branches; Handler would need editing per exception; `BUSY_STATUSES` array | Engine per task shape; `HasApiPresentation`; `TaskStatus::isBusy()` |
| LSP | `mixed $account` weaker than the already-typed `TaskActionHandlerInterface::handle(Account)` | FR-2.3 typing |
| ISP | `TsoAuthService` exposes `getCookieFile()` as public API for callers that only need «reset» | `TsoSessionInterface::resetSession()` |
| DIP | concrete `TsoAmfService`/`TsoAuthService` injected; no transport contract; static `BotLog::create` in services | `TsoClientInterface` + `TaskActivityLogger` as the only log writer |
| DRY | 5 copies of task finalization; 22 hand-built envelopes; `server_time` ×4; `is_array ? : json_decode` ×2+; ~3 100 duplicated analytics lines | `TaskStateWriter`; `ApiResponder`; resource-level `server_time`; `array` cast; shared components |
| YAGNI/Clean | ~1 500 lines of unreachable Blade; dead in-process sequence loop | FR-5.1, FR-2.1 |
