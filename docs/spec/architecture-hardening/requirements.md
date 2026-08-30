# Architecture Hardening — Requirements

Normative language: MUST / MUST NOT / SHOULD. Priority order per constitution §1:
explicit user requirement > this spec > constitution > existing convention.

---

## 1. Security & isolation (P0)

### FR-1.1 No secrets in the repository
The repository MUST NOT contain private keys or certificates.
`docker/nginx/certs/*.pem` MUST be removed from the working tree and from git
history, the certificate MUST be treated as compromised and reissued, and
`.gitignore` MUST exclude `docker/nginx/certs/*.pem`. `docker-compose*.yml` MUST
mount certificates from a host path or a named volume, documented in `README.md`.

**Acceptance:** `git ls-files | grep -E '\.(pem|key|crt)$'` returns nothing;
`docker compose config` resolves the certificate mount; `make prod-up` documented.

### FR-1.2 Single-operator invariant (revised by ADR-001)
The application is single-operator. No `user_id` columns, no policies and no ACL/RBAC
MUST be introduced — see `decisions.md`, ADR-001. The invariant MUST instead be made
explicit and tested:
- registration MUST remain first-user-only (it already is: `AuthController::register()`
  performs the existence check inside `DB::transaction` with `lockForUpdate()`);
- a test MUST assert a second registration is rejected on both the HTML and JSON paths;
- a route-driven test MUST assert every `auth:sanctum` route returns 401 for a guest,
  so newly added routes are covered automatically;
- public market endpoints MUST stay unauthenticated and MUST expose only the field set
  serialized by `PublicServerResource` / `MarketOfferResource`, asserted by exact JSON keys;
- constitution §6 MUST be amended so «account isolation» refers to game accounts, and the
  assumption plus its reversal path MUST be documented in the spec README.

**Acceptance:** Feature tests prove user B receives 403/404 for every account,
task and market-server endpoint owned by user A, including `execute`, `sync`,
`toggle`, `destroy`, `friendZone`, `updateSession`.

### FR-1.3 Encrypted credential storage
`Account::$casts` MUST cast `password`, `dso_auth_user` and `dso_auth_token` to
`encrypted`. A data migration MUST re-encrypt existing rows, and MUST be
reversible. Credentials MUST NOT appear in `BotLog` messages, `Log::` context or
API responses.

**Acceptance:** a Feature test writes an account, reads the raw column via the
query builder and asserts the stored value differs from the plaintext; a test
asserts no log record contains the password value.

### FR-1.4 Safe error contract
Domain exceptions MUST be rendered centrally in `App\Exceptions\Handler` into the
`ApiResponder` envelope with an explicit status code per exception type.
Controllers MUST NOT catch `Exception` to build error responses, and API responses
MUST NOT contain `$e->getMessage()` from non-domain exceptions. Diagnostic detail
MUST go to the log with redaction.

**Acceptance:** `AccountController::sync()` has no `try/catch`; a Feature test
forcing a transport failure asserts a stable localized message and a 502/500 body
without upstream text.

---

## 2. Backend design (P1)

### FR-2.1 Single sequence-execution engine
There MUST be exactly one implementation of sequence execution. The in-process
`TaskExecutionService::executeSequenceTask()` loop MUST be removed after its call
sites are verified, and `execute()` MUST delegate sequence work to the step-based
engine used by `ExecuteScheduledTaskJob`. `sleep()` MUST NOT be used inside a
request-scoped path.

### FR-2.2 Decompose `TaskExecutionService`
`TaskExecutionService` MUST become an orchestrator of ≤ 150 lines. Persistence of
task outcome, result summarization/truncation and activity logging MUST move to
dedicated collaborators injected by constructor. The task-finalization block MUST
exist exactly once. Result truncation limits MUST be defined once in configuration.

### FR-2.3 Strong typing
All `mixed $account` parameters MUST become `Account`. `AccountController::friendZone`
MUST accept `int $friendId`. New and touched PHP files MUST declare
`strict_types=1` and full parameter/return types.

### FR-2.4 Enums instead of magic strings
`App\Enums` MUST provide backed enums for `TaskStatus`, `TaskType`,
`ScheduleType`, `LogLevel` and `TaskResultPrefix`. Models MUST cast the
corresponding columns to those enums. String literals for these values MUST NOT
remain in `app/`. The database column values MUST NOT change (backward-compatible
API contract, constitution §10).

### FR-2.5 Domain exceptions, no suppression
Generic `throw new Exception(...)` in domain services MUST be replaced by existing
or new `App\Exceptions\*` types. `catch (Throwable) {}` suppression in
`executeSingleAction()` MUST be replaced by an explicit, narrow check that does not
depend on test data shape. `@` error suppression MUST be removed.

### FR-2.6 Encapsulated session lifecycle
`TsoAuthService` MUST expose `resetSession(Account $account): void`. No caller may
touch the cookie-file path. Retry codes, attempt count and backoff MUST come from
`config/game.php`.

### FR-2.7 One API contract: resources + HTTP semantics (revised by ADR-003)
The contract is `JsonResource` + HTTP status codes + centrally rendered exceptions.
- Hand-built `response()->json([...])` in `app/Http/Controllers/**` MUST be zero;
  `response()->json()` survives only inside `App\Exceptions\Handler`.
- `App\Support\Http\ApiResponder` MUST be deleted once its last caller is gone; the
  `{success, message}` envelope MUST NOT be extended to new endpoints.
- Status codes carry the outcome: `201` + `Location` on create, `200` + resource on
  update and synchronous actions, `202` on queued actions, `204` on delete, `422` on
  validation failure, `4xx/5xx` + `{message, code}` on domain failure.
- User-facing UI text MUST live in the SPA's `resources/js/lang/generated/{en,ru,uk}.json`;
  server-side `lang/*` keeps domain error messages only. No literal English in PHP.
- `server_time` MUST be emitted once through collection `meta`, never per action.

**Acceptance:** `grep -r "response()->json" app/Http/Controllers | wc -l` → 0;
`ApiResponder` no longer exists; per-endpoint contract tests assert status + JSON shape;
a test asserts the three locales have identical key sets.

### FR-2.8 Meaningful static analysis
`phpstan.neon` MUST analyse `app/`, `database/`, `routes/` and `tests/` at **level 6**
with the `larastan/larastan` extension enabled (approved dev dependency, ADR-004), with
the blanket `undefined static method` ignore removed. Baselining is allowed only
via an explicit `phpstan-baseline.neon` that shrinks per work package. Raising the
level MUST NOT be achieved by widening `ignoreErrors`.

**Acceptance:** `./vendor/bin/phpstan analyse` passes at the declared level with
the full `app/` path list; the baseline file line count is recorded per package in
`verification.md`.

---

## 3. Data & performance (P2)

### FR-3.1 Thin model, no hidden queries
`Account` MUST declare `strict_types=1`, cast `zone_data` to `array`, and derive
`avatar_id`, `building_count`, `server_name` from a single `ZoneSnapshot` value
object decoded once per model instance. `is_market_connected` MUST NOT run a query
per row: it MUST be provided by a relation with `withExists()` / eager loading, and
MUST NOT be an unconditional `$appends` entry.

**Acceptance:** a Feature test seeds 10 accounts with connections and asserts the
query count for `GET /api/accounts` is constant (`assertQueryCount`-style via
`DB::listen`), not linear.

### FR-3.2 Bounded collections (revised by ADR-005)
`GET /api/tasks` and `GET /api/logs` MUST be paginated with a bounded, validated
`per_page` (default and maximum in `config/`); the SPA MUST be updated in the same work
package. `GET /api/accounts` MAY keep returning the full set — the operator-owned account
list stays small — but MUST still return a `ResourceCollection` with `meta`, so adding
pagination later is not a breaking change. The constant-query-count requirement (FR-3.1)
applies regardless of row count.

### FR-3.3 Protocol boundary
The AMF value objects declared inside `app/Services/TsoAmfService.php` MUST move to
individual PSR-4 files under `App\Services\Amf\Vo\` with typed properties.
Outbound game I/O MUST sit behind a `TsoClientInterface` contract bound in
`TsoServiceProvider`, so tests fake the transport instead of the network. Protocol
field names, order and codes MUST NOT change (constitution §7); a fixture-based
regression test MUST prove byte-level equivalence of the encoded payload before and
after the move.

---

## 4. Frontend (P3)

### FR-4.1 Composition API and component budget
`Tasks.vue` MUST be converted to `<script setup>`. No route-level view SHOULD exceed
400 lines; extracted units go to `resources/js/components/<domain>/`.

### FR-4.2 API and state boundaries
No `axios` call may remain in `resources/js/views/**` or
`resources/js/components/**`. Requests MUST go through per-domain clients in
`resources/js/services/api/` layered on the existing `apiCacheService`, consumed by
composables in `resources/js/composables/` (`useAccounts`, `useTasks`,
`useTaskForm`, `useMarketAnalytics`, `useSyncPolling`). Each composable MUST handle
loading, empty, error, success and stale-response races (constitution §12).

### FR-4.3 Deduplicate analytics
`MarketAnalytics.vue` and `PublicMarketAnalytics.vue` MUST share chart, filter,
table and period components and a single composable parameterized by data source.
The public screen MUST keep exposing only the public field set.

### FR-4.4 No behavior or visual regressions
All items already checked in `docs/foundbugs.md` (spinners, blur overlays,
responsive layout, animated status border, localization, multi-select) MUST keep
working. Each frontend package MUST list the screens exercised manually.

---

## 5. Dead code & hygiene (P3)

### FR-5.1 Remove unreachable Blade UI
After confirming zero references, `resources/views/{accounts,logs,settings,tasks,
partials}/**`, `dashboard.blade.php`, `welcome.blade.php` and
`layouts/app.blade.php` MUST be deleted. `app.blade.php` and `auth/login.blade.php`
MUST stay. Deletion MUST be a separate commit (`chore:`) with no other changes.

### FR-5.2 Toolchain hygiene
`.phpunit.result.cache` MUST be untracked. `make install` MUST work on POSIX
shells. `docs/spec/solid-refactor/README.md` MUST use relative links instead of
`file:///C:/OSPanel/...`.

---

## 6. Non-functional requirements

- **NFR-1 Runtime:** PHP `^8.1` (no 8.2+ syntax), Laravel `^10.10`, Vue 3, Vite 6,
  Tailwind 3. No dependency added, removed or upgraded without explicit approval.
- **NFR-2 API compatibility:** response keys and status semantics stay
  backward-compatible unless a requirement above changes them explicitly; any
  change is listed in the work package.
- **NFR-3 Migrations:** additive, reversible, backfill-then-enforce; no edits to
  deployed migrations.
- **NFR-4 Gates:** every work package ends green on `php artisan test`,
  `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse`, `npm run build`.
- **NFR-5 Increment size:** each work package is independently shippable and
  revertible; no package mixes a behavior change with a mass reformat.
- **NFR-6 Commits:** Conventional Commits (`feat`, `fix`, `refactor`, `test`,
  `chore`), one concern per commit.

---

## 7. Owner decisions — resolved 2026-08-05

All blocking questions are answered; full records in `decisions.md`.

| ID | Question | Answer | Record |
| :--- | :--- | :--- | :--- |
| Q-1 | Single-operator or multi-user? | Single administrator; public market unauthenticated. No ownership layer. | ADR-001 |
| Q-2 | Certificates and history rewrite? | Test certs — delete, gitignore, `git filter-repo`, no reissue. | ADR-006 |
| Q-3 | Re-encryption window? | Not needed; credential loss acceptable, never was in production. | ADR-007 |
| Q-4 | PHPStan level? | `larastan` + level 6, shrinking baseline. | ADR-004 |
| Q-5 | Pagination / REST? | Tasks and logs paginated; accounts full set; pragmatic REST. | ADR-005, ADR-008 |
| Q-6 | Runtime version? | PHP 8.4 + latest stable Laravel, three staged packages. | ADR-002 |

### FR-6.1 Supported runtime (new, ADR-002)
The application MUST run on a supported PHP and Laravel version. PHP `^8.1` and Laravel
`^10.10` are both past end of support and MUST be upgraded to PHP 8.4 and the current
stable Laravel line, with Sanctum upgraded to match. The upgrade MUST be split into three
independently revertible packages (PHP → Laravel 11 → latest), each ending with all four
gates green. `AGENTS.md` §1 and constitution §3 MUST be amended — they currently forbid
PHP 8.2+ syntax.

### Original questions (kept for traceability)

| ID | Question | Blocks |
| :--- | :--- | :--- |
| Q-1 | Is the app single-operator or true multi-user? If single-operator, FR-1.2 becomes «scope everything to the single owner + document the assumption» instead of a full policy layer. | WP-2 |
| Q-2 | Certificate reissue and history rewrite (`git filter-repo`) — approved? History rewrite affects every clone. | WP-1 |
| Q-3 | Are stored game credentials still valid after re-encryption downtime, and is a maintenance window acceptable? | WP-3 |
| Q-4 | PHPStan target level: 5 (pragmatic) or 6+ with `larastan` (new dev dependency → needs §14 approval)? | WP-5 |
| Q-5 | May `GET /api/accounts|tasks|logs` switch to a paginated envelope (SPA updated in the same release), or must the flat array be preserved? | WP-8 |
