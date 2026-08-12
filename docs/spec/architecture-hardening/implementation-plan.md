# Architecture Hardening — Implementation Plan (rev. 2, after ADR-001 … ADR-008)

Every work package (WP) is independently shippable, independently revertible, and
ends with all four gates green:

```bash
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
```

Rule for the whole phase: **tests first for behavior, refactor second.** No package
mixes a behavior change with a mass reformat.

---

## Roadmap

| # | WP | Title | Priority | Requirements | Est. | Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| 1 | WP-1 | Purge committed TLS material, mount certs at runtime | P0 | ADR-006, FR-1.1 | S | DONE |
| 2 | WP-11 | Delete unreachable Blade UI + repo hygiene | P3 | FR-5.1, FR-5.2 | S | DONE |
| 3 | WP-2' | Assert the single-operator invariant | P0 | ADR-001 | S | DONE |
| 4 | WP-U1 | PHP 8.1 → 8.3 (Laravel 10 unchanged) | P0 | ADR-002 | M | DONE |
| 5 | WP-U2 | Laravel 10 → 11 (skeleton restructure, Sanctum 4) | P0 | ADR-002 | L | DONE |
| 6 | WP-U3 | Laravel 11 → latest + PHP 8.4 | P0 | ADR-002 | M | DONE |
| 7 | WP-5 | larastan + PHPStan level 6 over the whole app | P1 | ADR-004 | M | DONE |
| 8 | WP-4 | Central safe error contract | P0 | FR-1.4, FR-2.5 | M | DONE |
| 9 | WP-3 | Encrypted credentials at rest | P0 | ADR-007, FR-1.3 | S | DONE |
| 10 | WP-6 | Backed enums for task/log domain states | P1 | FR-2.4, FR-2.3 | M | DONE |
| 11 | WP-7 | Task execution decomposition, one engine | P1 | FR-2.1, FR-2.2, FR-2.6 | L | DONE |
| 12 | WP-15 | Regression tests for the two open task bugs | P1 | audit §E | M | DONE |
| 13 | WP-8 | Resource-based API contract, i18n, pagination | P1/P2 | ADR-003, ADR-005 | L | DONE |
| 14 | WP-9 | Thin `Account` model, kill the N+1 | P2 | FR-3.1 | M |
| 15 | WP-10 | Protocol boundary: typed AMF VOs + `TsoClientInterface` | P2 | FR-3.3 | L |
| 16 | WP-12 | Frontend API clients + composables | P3 | FR-4.2 | L |
| 17 | WP-13 | `Tasks.vue` → `<script setup>` + component split | P3 | FR-4.1, FR-4.4 | L |
| 18 | WP-14 | Deduplicate the two market analytics screens | P3 | FR-4.3, FR-4.4 | L |

S ≈ half a day, M ≈ 1–2 days, L ≈ 3–5 days.

**Why this order.** Cheap safety first (WP-1, WP-11, WP-2'), then the runtime
upgrade while the codebase is still small — deleting 1 500 dead Blade lines before
the Laravel 11 skeleton restructure keeps the upgrade surface minimal, and doing the
restructure before WP-7 avoids touching the same task files twice. Static analysis
(WP-5) lands right after the upgrade because the `larastan` version depends on the
Laravel line; from then on every later package is checked by a gate that actually
checks something.

---

## WP-1 — Purge committed TLS material (ADR-006)
1. `git rm --cached docker/nginx/certs/*.pem`; add `docker/nginx/certs/*.pem` to `.gitignore`.
2. `git filter-repo --path docker/nginx/certs --invert-paths`; force-push; re-clone locally.
3. `docker/entrypoint.sh`: generate a self-signed pair for local dev, fail fast with a clear message when production certs are missing.
4. `docker-compose.prod.yml`: mount certs from a host path / named volume; document in `README.md`.
5. Add a guard to `make lint`: `! git ls-files | grep -qE '\.(pem|key|crt)$'`.

**Done when:** history contains no `*.pem`; the guard fails a deliberate test commit.
**Commit:** `chore(security)!: remove committed TLS material and mount certs at runtime`

---

## WP-11 — Delete unreachable Blade UI + hygiene
1. Re-verify zero references (`view('...')`, `@include`, `@extends`) for `accounts/*`, `logs/*`, `settings/*`, `tasks/*`, `partials/*`, `dashboard`, `welcome`, `layouts/app`.
2. Delete them; keep `app.blade.php` and `auth/login.blade.php`.
3. `git rm --cached .phpunit.result.cache`; POSIX `make install` (`[ -f .env ] || cp .env.docker .env`).
4. Relative links in `docs/spec/solid-refactor/README.md` instead of `file:///C:/OSPanel/...`.

**Done when:** deletion-only commit, gates green, login + SPA boot verified manually.
**Commits:** `chore: remove unreachable legacy blade views`, `chore: fix posix make install and untrack phpunit cache`

---

## WP-2' — Assert the single-operator invariant (replaces the ownership layer)
No migrations, no policies, no `user_id` — see ADR-001. What ships instead:
1. Feature test: registration succeeds exactly once; the second attempt is rejected in both HTML and JSON paths.
2. Feature test: concurrent registration (two requests in one test through the transaction path) still yields one user.
3. Feature test: every `auth:sanctum` route returns 401 for a guest — a data-provider test driven by `Route::getRoutes()` so new routes are covered automatically.
4. Feature test: public market endpoints are reachable without auth **and** expose only the public field set (assert exact JSON keys of `PublicServerResource` / `MarketOfferResource`).
5. Amend `docs/spec/constitution.md` §6 and add the assumption + reversal path to `README.md`.

**Done when:** the route-driven 401 test covers every protected route; the constitution no longer contradicts the code.
**Commits:** `test(auth): lock the single-operator invariant`, `docs: record single-operator assumption in the constitution`

---

## WP-U1 — PHP 8.3 (ADR-002)
1. `composer.json`: `"php": "^8.3"`; `Dockerfile` + `docker/php/php.ini` to the 8.3 image; update `.github` CI matrix if present.
2. `composer update` within Laravel 10 constraints; run the suite with `error_reporting(E_ALL)` and fix deprecations — expect implicit nullable parameters (`?Type $x = null`) and dynamic property creation in the older services (`TsoAmfService`, `TsoAuthService`).
3. Update `AGENTS.md` §1 and constitution §3 — they currently forbid PHP 8.2+ syntax.

**Done when:** gates green on 8.3; no deprecation output in the test run.
**Commit:** `chore(runtime): upgrade to php 8.3`

---

## WP-U2 — Laravel 11 (the expensive step)
1. Follow the official upgrade guide; adopt the new skeleton: `bootstrap/app.php` replaces `app/Http/Kernel.php` and `app/Console/Kernel.php`.
2. Re-register the 11 middleware entries (`SetLocale`, `HttpCacheHeaders`, `TrustProxies`, …) through the application builder; verify middleware **order** explicitly — `SetLocale` must still run before controllers.
3. Re-register the 5 providers (`AppServiceProvider`, `RouteServiceProvider`, `MarketServiceProvider`, `TaskServiceProvider`, `TsoServiceProvider`) in `bootstrap/providers.php`; `config/app.php` no longer holds them.
4. Sanctum 3 → 4; re-verify the session-cookie SPA auth path end to end.
5. Re-check the scheduler: `routes/console.php` / `withSchedule()` replaces `Console\Kernel::schedule()` — the task scheduler and log-retention cron must keep firing.
6. Queue config: confirm the `tso-tasks`, `tso-market`, account-sync and log-cleanup queues are still consumed by the worker command.

**Done when:** gates green; manual check of login, SPA boot, one task run, one market sync, one scheduled fire.
**Risk:** highest-risk package after WP-7 — ship alone, review the whole diff.
**Commit:** `chore(runtime)!: upgrade to laravel 11`

---

## WP-U3 — Latest Laravel + PHP 8.4
Small follow-up once 11 is in: bump constraints, run the guide's diff, move to PHP 8.4 image, re-run gates. Adopt 8.4 ergonomics only where they remove boilerplate (property hooks in VOs, asymmetric visibility) — no speculative rewrites.
**Commit:** `chore(runtime): upgrade to laravel 12 and php 8.4`

---

## WP-5 — Real static analysis (ADR-004)
1. `composer require --dev larastan/larastan` (version matching the Laravel line).
2. `phpstan.neon`: include the larastan extension, `paths: [app, database, routes, tests]`, `level: 6`, delete the blanket `undefined static method` ignore.
3. Generate `phpstan-baseline.neon`; record its line count in `verification.md` as the debt number.
4. Fix the cheap classes immediately: missing return types, missing array shapes, `mixed` parameters (`TaskExecutionService`, `AccountController::friendZone`).
5. Update `Makefile`/`AGENTS.md` so the reported gate matches reality.

**Done when:** the gate fails on a deliberately introduced type error; baseline recorded.
**Commit:** `chore(quality): analyse the whole app with larastan level 6`

---

## WP-4 — Central safe error contract
1. `App\Exceptions\Contracts\HasApiPresentation` (`userMessage(): string`, `httpStatus(): int`, `context(): array`); implement on the 11 existing exceptions with localized keys.
2. `Handler::register()` — one `renderable()` producing `{message, code}` at the declared status; generic localized 500 + redacted log for everything else.
3. Remove the `try/catch` from `AccountController::sync()`; `AccountSyncService` throws a domain exception instead.
4. Replace `throw new Exception("Task #... is not a sequence task.")` with a domain exception; remove the `@` suppression and the `catch (Throwable) {}` swallow in `executeSingleAction()` (explicit AMF-shape check instead).

**Done when:** `grep -rn 'getMessage()' app/Http/Controllers` is empty; each exception maps to its declared status in a test.
**Commits:** `feat(api): render domain exceptions centrally`, `refactor(tasks): replace generic exceptions and error suppression`

---

## WP-3 — Encrypted credentials (ADR-007)
1. `Account::$casts`: `password`, `dso_auth_user`, `dso_auth_token` → `encrypted`.
2. Reversible chunked data migration; rows that cannot be decoded are nulled and flagged for re-login (acceptable per ADR-007).
3. Audit `TsoAuthService`, `AccountSyncLogger`, `TaskActivityLogger` for credential interpolation; add a redaction helper.

**Tests:** raw column ≠ plaintext; model round-trip returns plaintext; no log line contains the secret.
**Commit:** `feat(security): encrypt stored game credentials at rest`

---

## WP-6 — Enums
1. `app/Enums/{TaskStatus,TaskType,ScheduleType,LogLevel,TaskResultPrefix}.php`, values copied verbatim from the migrations.
2. Cast in `ScheduledTask` / `BotLog`; stored strings unchanged.
3. Replace literals in `TaskExecutionService`, `ScheduledTaskService`, `ExecuteScheduledTaskJob`, `TaskSchedulerEngine`, Form Requests, Resources; `Rule::enum()` in validation.
4. `TaskStatus::isBusy()` replaces `ScheduledTaskService::BUSY_STATUSES`.
5. Finish the typing pass: `mixed $account` → `Account`, `mixed $friendId` → `int`.

**Tests:** existing suite unchanged (contract proof) + an enum-value test pinning the exact stored strings.
**Commit:** `refactor(tasks): replace magic strings with backed enums`

---

## WP-7 — Task execution decomposition (the core)
1. **Characterization tests first:** current observable behavior of `execute()`, `executeSequenceStep()`, retry on 1005/1012, `once` deactivation, token mismatch, partial results.
2. Verify call sites of `executeSequenceTask()`; if reachable only through `execute()`, delete it and route sequences to the step engine.
3. Extract `TaskStateWriter` — the single owner of the finalization block; one truncation limit in `config/game.php`.
4. Extract `TaskResultSummary`, `StepResultCollection`, `StepOutcome`.
5. Extract `TaskExecutionGuard`, `ActionRetryPolicy`, `GameResponseValidator`, `SequenceStepExecutor`, `SingleActionExecutor`.
6. `TsoAuthService::resetSession()`; remove `getCookieFile()` from the public surface and the `@unlink` from the caller.
7. Move `BotLog` writes into `TaskActivityLogger`; job budget constants to config.
8. `TaskExecutionService` becomes `TaskRunner` (≤ 150 lines).

**Done when:** finalization exists once (grep), no `sleep()` outside the job, characterization tests green.
**Commits:** `test(tasks): characterize task execution`, `refactor(tasks): extract state writer and result summary`, `refactor(tasks): single sequence engine`, `refactor(tso): encapsulate session reset`

---

## WP-15 — Regression tests for the open bugs
Targets the unchecked items in `docs/foundbugs.md`: manual run leaves the task stuck on «выполнено», steps not finishing, building name lost (only grid persisted).
Reproduce as failing tests against the WP-7 engine (stale-recovery vs delayed hand-off heartbeat, duplicate delivery, token mismatch), then fix.
**Commits:** `test(tasks): reproduce stuck manual execution`, `fix(tasks): …`

---

## WP-8 — Resource-based API contract (ADR-003, ADR-005)
Ship endpoint group by endpoint group (accounts → tasks → settings/logs → market), backend + SPA together.
1. Delete hand-built `response()->json` from the 5 controllers; return resources with correct status codes (`201` + `Location`, `204` on delete, `202` on queued actions, `200` + resource on sync actions).
2. `server_time` moves to collection `meta` via one helper (`additional(['meta' => ...])`), removed from the 4 actions.
3. Move UI messages out of PHP into the SPA's existing `resources/js/lang/generated/{en,ru,uk}.json`; keep server-side `lang/*` for domain error messages only; add a test asserting identical key sets across the three locales.
4. Paginate `GET /api/tasks` and `GET /api/logs` with a validated bounded `per_page`; accounts stay a full `ResourceCollection` (ADR-005).
5. Delete `App\Support\Http\ApiResponder` once the last caller is gone.

**Done when:** `grep -r "response()->json" app/Http/Controllers | wc -l` → 0; contract tests per endpoint assert status + JSON shape; SPA no longer reads `data.success`.
**Commits:** `refactor(api): return resources and http status codes`, `feat(i18n): move ui messages to the spa`, `feat(api): paginate tasks and logs`, `chore(api): drop ApiResponder`

---

## WP-9 — Thin `Account` model
1. `declare(strict_types=1);`; cast `zone_data` to `array`.
2. `App\Support\Zone\ZoneSnapshot` + memoized `Account::snapshot()`; accessors delegate; remove the three `try/catch` swallows.
3. `marketServerConnections(): HasMany`; drop `is_market_connected` from `$appends`; serve it from `withExists()`; `AccountResource` reads the exists flag.
4. Remove the duplicated `is_array ? : json_decode` from `AccountController::zone()`.

**Tests:** `DB::listen` query-count test proving constant queries for 10 accounts; snapshot unit tests incl. malformed JSON.
**Commit:** `refactor(accounts): extract zone snapshot and remove per-row queries`

---

## WP-10 — Protocol boundary
1. **Fixture snapshot test first:** exact encoded payload bytes for a representative call.
2. Move the four in-file classes to `App\Services\Amf\Vo\*` (one file each), typed properties, wire format byte-identical.
3. `TsoClientInterface` + `HttpTsoClient` (timeouts/retries/base URLs from `config/game.php`); `TsoSessionInterface` for auth; bind in `TsoServiceProvider`.
4. `TsoAmfService` keeps message construction only.

**Done when:** the snapshot test passes unchanged; no live network call in the suite.
**Commits:** `test(tso): snapshot encoded amf payloads`, `refactor(tso): extract typed value objects`, `refactor(tso): introduce transport contract`

---

## WP-12 — Frontend API + composable boundary
1. `resources/js/services/api/{accounts,tasks,market,settings,logs}.js` over the existing `apiCacheService`.
2. `composables/useAsyncResource.js` — loading/empty/error/success + stale-response guard (request-id compare).
3. `useAccounts`, `useTasks`, `useTaskForm`, `useMarketAnalytics`, `useSyncPolling`.
4. Migrate views/components; remove every `axios.` import from `views/**` and `components/**`.

**Done when:** `grep -rn 'axios\.' resources/js/views resources/js/components` is empty; build green; spinner/blur/stale behavior verified on 6 screens.
**Commit:** `refactor(ui): extract api clients and async composables`

---

## WP-13 — `Tasks.vue` split
1. Convert `<script>` → `<script setup>` mechanically, no logic change; verify.
2. Extract `TaskList`, `TaskCard`, `ActionSequenceBuilder`, `ActionRow`, `BuildingPicker`, `SpecialistPicker`, `BuffPicker`, `SchedulePicker` into `components/tasks/`.
3. View keeps layout + composable wiring (≤ 400 lines).

**Done when:** no regression in the checked `docs/foundbugs.md` behaviors; build green.
**Commits:** `refactor(ui): migrate tasks view to script setup`, `refactor(ui): split task planner into components`

---

## WP-14 — Deduplicate market analytics
1. Extract `MarketFilters`, `MarketPeriodPicker`, `MarketOffersTable`, `PopularItemsPanel`, `ArbitragePanel`.
2. One `useMarketAnalytics(apiClient)` parameterized by data source.
3. Both views become thin wrappers; the public one keeps the reduced field set.

**Done when:** combined line count drops ≥ 60 %; both screens verified; build green.
**Commit:** `refactor(ui): share market analytics components`

---

## Definition of done for the phase

1. No secret material in the working tree or in git history; a guard prevents regression.
2. Supported runtime: PHP 8.4 + current Laravel, all gates green.
3. Single-operator invariant asserted by tests; constitution matches the code.
4. Credentials encrypted at rest; no secret in logs or responses.
5. One error contract, one response style (resources + status codes), UI text in the SPA's i18n, `ApiResponder` gone.
6. One sequence engine, one task-state writer, no magic domain strings.
7. larastan level 6 over `app/` with a shrinking baseline.
8. Constant query count for account listings; tasks and logs paginated.
9. Game protocol behind a contract, faked in tests, byte-identical on the wire.
10. No axios in views/components; no route view above ~400 lines; `Tasks.vue` on `<script setup>`.
11. `docs/foundbugs.md` items for SOLID refactor, docker and dead code closed.
