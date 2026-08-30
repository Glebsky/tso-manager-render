# Architecture Decision Records — Phase 2

Decisions agreed with the owner on 2026-08-05. These records have priority over
the original wording in `requirements.md` / `design.md` (constitution §1:
explicit user requirement > spec).

---

## ADR-001 — Single-operator model, no ownership layer

**Context.** The app has no `user_id` on `accounts`, `scheduled_tasks`,
`market_server_connections`, no policies, and no `authorize()` calls. The original
audit rated this P0 assuming multi-user.

**New evidence.** `AuthController` already enforces a single administrator:
`showLogin()` redirects to registration while `! User::query()->exists()`,
`showRegistration()` redirects to login once a user exists, and `register()` runs
the first-user check inside `DB::transaction` with `lockForUpdate()`. So the system
cannot get a second account through the UI, and the «any authenticated user sees
everything» scenario has no way to occur.

**Decision.** The application is **single-operator by design**. No `user_id`
columns, no policies, no ACL/RBAC. Building them now would be overengineering: an
authorization layer that always resolves to the same principal adds indirection,
migrations and tests while removing no real risk.

**Consequences / what is still required**
1. The assumption becomes an explicit, tested invariant, not an accident:
   - a Feature test asserting a second registration attempt is rejected
     (both HTML and JSON paths), including a concurrent-request case;
   - a Feature test asserting every `auth:sanctum` endpoint returns 401 for guests;
   - a `users` table invariant check in the health endpoint or a console command.
2. `docs/spec/constitution.md` §6 is amended: «account isolation» refers to
   **game accounts**, not application users; «every mutation verifies ownership» is
   replaced by «every mutation runs as the single authenticated operator and every
   account-scoped operation resolves an explicit game account».
3. The public market portal stays unauthenticated and MUST expose only the fields
   serialized by `PublicServerResource` / `MarketOfferResource`, with bounded
   pagination and query-cost review — this is the only real multi-tenant surface.
4. **Migration path kept open:** if a second operator is ever needed, the work is
   one additive migration + `scopeOwnedBy()` + three policies. Documented here so
   the decision is reversible rather than forgotten.

**Status:** accepted. Supersedes FR-1.2 (WP-2 is replaced by WP-2').

---

## ADR-002 — Runtime upgrade: PHP 8.4 + latest Laravel

**Context.** The project pins PHP `^8.1` and Laravel `^10.10`. Both are past their
support window: PHP 8.1 reached end of security support at the end of 2025, and
Laravel 10 left active support long before that. `AGENTS.md` and the constitution
currently forbid PHP 8.2+ syntax.

**Decision.** Upgrade. This is not a «nice to have»: staying on an unsupported
runtime is itself a security finding, and it blocks `larastan` 3.x, modern PHPStan
levels and current Sanctum. Target: **PHP 8.4** and the **latest stable Laravel**
(verify the exact minor at execution time), Sanctum matching that Laravel line.

**Sequencing (three separate, revertible packages).**
1. **WP-U1 — PHP only.** Bump `composer.json` to `^8.3`, `Dockerfile` /
   `docker/php/php.ini` to the new image, keep Laravel 10 (it supports 8.3). Fix
   deprecations (notably implicit nullable parameters and dynamic properties).
   Cheapest possible step, immediately restores a supported runtime.
2. **WP-U2 — Laravel 11.** This is the expensive one: the skeleton changes
   (`bootstrap/app.php` replaces `app/Http/Kernel.php` and `app/Console/Kernel.php`,
   provider registration moves out of `config/app.php`, `$middleware` arrays move to
   the application builder). The project has 5 custom service providers
   (`MarketServiceProvider`, `TaskServiceProvider`, `TsoServiceProvider`,
   `AppServiceProvider`, `RouteServiceProvider`) and 11 middleware entries — all
   must be re-registered. Sanctum 3 → 4.
3. **WP-U3 — Laravel 12 (or the current latest) + PHP 8.4.** Small step once 11 is in.

**Ordering versus the refactor.** Upgrades go **before** WP-6/WP-7 and after WP-1
(certs) and WP-11 (dead-code deletion). Rationale: deleting 1 500 dead Blade lines
first shrinks the upgrade surface; doing the skeleton restructure after the task
engine refactor would produce a merge conflict across the same files twice.

**Risk control.** Each step is a separate branch with all four gates green; the
98-test suite is the safety net (that is precisely why WP-5 — real static analysis —
follows immediately after, not before: `larastan` version depends on the Laravel line).

**Consequences.** `AGENTS.md` §1 and constitution §3 must be rewritten («PHP 8.1,
no 8.2+ syntax» becomes «PHP 8.4»). New syntax then allowed and encouraged where it
removes boilerplate: `readonly` classes, enums in constants, `never`, first-class
callable syntax, property hooks / asymmetric visibility (8.4).

**Status:** accepted, approved as an explicit exception to constitution §14.

---

## ADR-003 — API contract: JsonResource + HTTP semantics; `ApiResponder` retired

**Context.** Three competing styles exist: `ApiResponder` (11 files), hand-built
`response()->json` (22 call sites) and `JsonResource` (8 resources). The owner asked
which is the professional choice.

**Decision.** The contract is **JsonResource + HTTP status codes + centrally
rendered exceptions**. `ApiResponder` is a transition shim and is deleted at the end
of the phase; `response()->json()` survives only inside `App\Exceptions\Handler` and
for genuinely non-resource payloads.

**Rationale.** A `{success, message}` envelope duplicates information HTTP already
carries: `success` restates the status code, and `message` is presentation text that
belongs to the frontend, which already owns `resources/js/lang/generated/{en,ru,uk}.json`.
An envelope makes every client parse the body to learn whether the call worked, and
it is exactly why English strings like `'Account added.'` leaked into PHP. Resources
also give a real place for `meta`, which removes the `server_time` duplication.

**Target contract**

| Case | Response |
| :--- | :--- |
| `GET` one | `200` + `AccountResource` |
| `GET` many | `200` + `AnonymousResourceCollection` with `data` + `meta` (pagination, `server_time`) |
| `POST` create | `201` + resource + `Location` header |
| `PUT` / `PATCH` | `200` + resource |
| `DELETE` | `204`, empty body |
| Action, synchronous (`toggle`) | `200` + resource — the new state *is* the answer |
| Action, queued (`execute`, `sync`) | `202 Accepted` + resource (status `queued`) |
| Validation failure | `422` + Laravel's `{message, errors}` |
| Domain failure | `4xx/5xx` + `{message, code}` from the Handler, message localized server-side |

`server_time` is emitted once via a `JsonResource::additional(['meta' => ...])` helper
(or a response middleware), never per action.

**Frontend consequence.** The SPA stops reading `response.data.success` /
`.message` and switches to status codes plus its own i18n. This is a breaking client
change, so backend and frontend land in the same work package (WP-8), one endpoint
group at a time, with the old keys kept for one release only if needed.

**Status:** accepted. Amends FR-2.7.

---

## ADR-004 — Static analysis: larastan, level 6, shrinking baseline

**Context.** The owner asked what «decorative PHPStan gate» means and whether to
target level 5 or 6.

**What the current config actually does.**
```neon
level: 0        # weakest of 0–10: only «this function does not exist»-class errors
paths:          # 3 files out of ~150 are analysed at all
  - app/Services/MarketCacheService.php
  - app/Http/Middleware/HttpCacheHeaders.php
  - app/Models/Setting.php
ignoreErrors:
  - '#Call to an undefined static method App\Models\...#'   # silences Eloquent errors wholesale
```
So `./vendor/bin/phpstan analyse` reports «0 errors» because it looks at ~2 % of the
code with all checks that matter turned off. `Makefile`, `AGENTS.md` §3 and
`docs/spec/solid-refactor/verification.md` present that output as a passed quality
gate — the number is true and meaningless. The `ignoreErrors` line exists because
PHPStan does not understand Eloquent's magic statics without a Laravel extension;
the correct fix is the extension, not the suppression.

**Decision.** Add `larastan/larastan` as a dev dependency (version matching the
Laravel line after ADR-002 — 3.x for Laravel 11+). Analyse `app`, `database`,
`routes`, `tests` at **level 6**, drop the blanket `ignoreErrors`, and record the
initial `phpstan-baseline.neon` size as the debt number. Level 6 adds missing-type
reporting, which is what actually catches the `mixed $account`-style erosion found in
the audit — level 5 would let it through. Level 8 (strict null) stays as a later goal.

**Rule.** The baseline may only shrink. Raising the level by widening ignores is
forbidden (constitution §14: no bypassing static analysis rules).

**Status:** accepted. Amends FR-2.8.

---

## ADR-005 — Pagination only where the data grows

**Decision.** `GET /api/accounts` keeps returning the full collection: the owner
confirms the number of game accounts stays small and it is a fixed, operator-owned
set. `GET /api/tasks` and `GET /api/logs` are paginated — tasks accumulate per run
and `bot_logs` grows continuously (there is already a log-retention cleanup service,
which is evidence of unbounded growth).

Even the unpaginated endpoint returns a `ResourceCollection` with `meta`, so adding
pagination later is not a breaking change. The N+1 fix (WP-9) is unaffected and stays
mandatory: constant query count matters more than row count here.

**Status:** accepted. Amends FR-3.2.

---

## ADR-006 — Certificates: delete and rewrite history

**Context.** `docker/nginx/certs/privkey.pem` and `fullchain.pem` are test material,
not production secrets, but the owner wants a repository that is presentable.

**Decision.** Remove the files, add `docker/nginx/certs/*.pem` to `.gitignore`, and
rewrite history with `git filter-repo --path docker/nginx/certs --invert-paths`.
Because the app was never in production and the clone set is effectively one machine,
the usual objection to history rewriting does not apply. No reissue is needed for
throwaway test certificates, but the entrypoint must generate a self-signed pair
locally and fail fast with a clear message when real certificates are absent, and
`README.md` must document the host mount.

**Also in scope:** `git ls-files` must stay free of `*.pem|*.key|*.crt`, and a CI
check (or a `make lint` step) should assert that — a clean history is only clean until
the next commit.

**Status:** accepted.

---

## ADR-007 — Credential encryption without a maintenance window

**Context.** The app has never been in production; losing stored game credentials is
acceptable.

**Decision.** Apply the `encrypted` casts to `password`, `dso_auth_user`,
`dso_auth_token`. The re-encryption data migration is still written (idempotent,
reversible, chunked) because it is cheap and required for any future environment, but
it is allowed to fall back to «null out and re-login» for rows it cannot decode. No
maintenance window planning is required for this phase.

**Status:** accepted. Simplifies WP-3.

---

## ADR-008 — REST pragmatism inside Laravel conventions

**Decision.** Follow REST where it is free (resource nouns, correct verbs, correct
status codes, `204` on delete, `202` on queued work) and follow Laravel conventions
where REST purism would fight the framework. Concretely: keep
`POST /api/accounts/{account}/sync`, `POST /api/tasks/{task}/execute` and
`POST /api/tasks/{task}/toggle` as action sub-resources instead of inventing
`/api/sync-jobs` resources — they are non-idempotent operations on an existing
resource, `POST` is the correct verb, and Laravel's route-model binding keeps them
type-safe. No HATEOAS, no custom hypermedia format.

**Status:** accepted.
