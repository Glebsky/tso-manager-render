# Project Constitution

## 1. Authority

- This file defines project-wide engineering constraints and is read on demand for complex architectural tasks or upon explicit user request.
- `AGENTS.md` defines the compact daily operating ruleset.
- A task-specific spec in `docs/spec/<feature>/` may refine behavior but may not weaken safety, security, isolation, or verification rules.
- Priority: explicit user requirement > accepted feature spec > this constitution > existing convention.
- When sources conflict, stop and surface the conflict. Do not silently choose.

## 2. Product Boundary

The application manages The Settlers Online accounts without launching the game client. It provides:

- Laravel JSON API and session-authenticated admin SPA.
- Vue public market analytics portal.
- Multi-account synchronization and scheduled game actions.
- Reverse-engineered game authentication/protocol integration.
- Market synchronization, history, and arbitrage analytics.

Game protocol behavior is an external contract derived from observed traffic and the reference implementation: `https://github.com/fedorovvl/tso_client`.

## 3. Runtime Contract

- PHP `^8.1`; Laravel `^10.10`.
- PostgreSQL in deployed environments; SQLite in test configuration.
- Redis for cache, sessions, queues, throttling, and account-scoped runtime state.
- Vue 3 Composition API, Vue Router, Axios, Vite 6, Tailwind CSS 3.
- PHPUnit 10, Laravel Pint, PHPStan.
- Do not require PHP 8.2+ or change runtime versions without approval.
- Do not add/replace/upgrade dependencies without approval and a documented need.

## 4. Repository Map

```text
app/Console/Commands/       CLI orchestration
app/Http/Controllers/       HTTP validation/orchestration
app/Http/Resources/         API serialization
app/Jobs/                   queued execution
app/Models/                 Eloquent persistence model
app/Services/               business logic and external integrations
app/Services/Lang/          language import/export
config/                     environment-backed configuration
database/migrations/        schema and indexes
docs/spec/<feature>/        feature requirements/design/verification
lang/                       server translations
resources/js/components/    reusable/presentational Vue components
resources/js/views/         route-level Vue screens
resources/js/services/      frontend service boundaries
resources/js/lang/          frontend localization
routes/                     HTTP/console entry points
tests/Unit/                 isolated logic tests
tests/Feature/              Laravel/integration behavior tests
tests/Fixtures/             deterministic external data samples
```

Do not reference aspirational directories as if they exist. Create a new layer only after explicit architectural approval.
Never scan or inspect vendor/generated directories (`vendor/`, `node_modules/`, `bootstrap/cache/`, `storage/`, `public/build/`).

## 5. Dependency Rules

Allowed direction:

```text
HTTP / Console / Jobs -> Services -> Models, framework adapters, external clients
Vue views -> components + frontend services -> JSON API
```

Rules:

- Controllers validate, authorize, call a service, and return a response/resource.
- Services own workflows, transactions, external I/O, and reusable business logic.
- Jobs contain queue metadata and delegation; idempotency belongs in the workflow.
- Models contain casts, relationships, scopes, and small persistence invariants.
- Resources define public API shape; do not expose raw internal payloads or secrets.
- Vue components do not implement game protocol, auth, or raw persistence semantics.
- Avoid circular dependencies, service locators, static mutable state, and hidden globals.
- Prefer direct, existing abstractions. Add an interface only at a real replaceable boundary or when tests require it.
- No repository wrapper over Eloquent without demonstrated value.

## 6. Single-Operator Invariant & Account Scope (ADR-001)

- The application is single-operator by design. Application user registration is restricted to the initial operator.
- "Account isolation" refers to game accounts, not application users.
- Every protected mutation runs as the single authenticated operator, and every account-scoped operation receives or resolves an explicit game account.
- Never trust account, user, server, price, resource, or task identifiers from the client.
- Queue payloads carry stable IDs, not authenticated user/session objects.
- Cache, lock, throttle, session, and idempotency keys include the relevant account/server identity.
- One game account's failure, lock, or rate limit must not block or expose another account.
- Public market endpoints remain unauthenticated and expose only fields explicitly serialized for public use (`PublicServerResource` / `MarketOfferResource`).


## 7. Game Protocol & External I/O

- Treat endpoints, AMF/protocol fields, action codes, ordering, and payload shapes as contracts.
- Change a protocol contract only from an explicit requirement or captured evidence; add a fixture/regression test.
- Centralize outbound game/server I/O in dedicated services.
- Configure base URLs, timeouts, retry limits, and throttles; no scattered literals.
- Bound retries; use exponential backoff with jitter for transient failures.
- Retry only idempotent operations unless an idempotency mechanism prevents duplicates.
- Validate status, content type, required fields, and malformed/partial responses.
- Apply per-account/server throttling to loops and background work.
- Never make live external requests in automated tests.

## 8. Data & Migrations

Before changing queries or schema:

1. Read the model, migration history, query call sites, and expected cardinality.
2. Inspect existing primary, unique, foreign, and composite indexes.
3. Check filter, join, grouping, and ordering columns against index order.
4. Consider PostgreSQL behavior even when tests use SQLite.

Rules:

- Use migrations for schema changes; never edit an already-deployed migration unless explicitly confirmed safe.
- Prefer additive, backward-compatible, reversible migrations.
- Destructive operations, column type narrowing, data rewrites, and constraint drops require approval, backup/rollback notes, and staged deployment planning.
- Add indexes for evidenced access patterns; avoid redundant indexes and unbounded indexed text.
- Enforce true invariants with database constraints where practical.
- Wrap multi-record invariants in transactions; keep external I/O outside long transactions.
- Avoid N+1 queries; select only required data for high-volume paths.
- Paginate or stream unbounded datasets.
- Do not use raw SQL interpolation. Bind values; whitelist identifiers/operators.

## 9. Secrets & Privacy

- `.env` and secret stores are read-only for agents.
- New variables go to `.env.example` with safe placeholders and matching `config/*` entries.
- Application code reads configuration via `config()`, not direct `env()` calls outside `config/`.
- Credentials and tokens must use encrypted storage/casts where persistence is required.
- Never log passwords, cookies, authorization headers, session IDs, tokens, raw credentials, or full sensitive upstream payloads.
- Redact sensitive fields before interpolation or structured logging.
- Do not expose exception internals or upstream secrets in API responses.
- Use least privilege for database, Redis, queue, filesystem, and external-service access.

## 10. API Contract

- Protected admin API remains under `auth:sanctum`; mutating web requests retain CSRF protection.
- Authenticate and authorize before remote I/O or mutation.
- Validate all input with bounded lengths, enums/allowlists, numeric ranges, and nested shape rules.
- Keep response fields and status semantics backward-compatible unless the task explicitly changes the contract.
- Serialize stable responses through resources or an established response mapper.
- Errors must be safe, actionable, and consistent; log diagnostic context without secrets.
- Public endpoints require explicit data-minimization review, abuse controls, bounded pagination, and query-cost review.

## 11. Queues, Scheduling & Idempotency

- Jobs must be safe under retries, duplicate delivery, timeout, and worker restart.
- Use account/server-scoped locks where concurrent execution can corrupt state or duplicate actions.
- Locks must have bounded TTL and release safely.
- Define timeout, retry, backoff, and terminal-failure behavior explicitly.
- Scheduler commands must avoid overlapping runs and unbounded fan-out.
- Do not swallow failures. Record safe context and allow queue failure handling to work.
- Do not dispatch one job per unbounded row without chunking and backpressure.

## 12. Frontend Contract

- Use Vue 3 Composition API and `<script setup>` for new/edited components.
- Route-level orchestration belongs in views; reusable UI belongs in components; API calls belong in service/composable boundaries.
- Preserve the existing Axios, router, localization, Vite, and Tailwind stack.
- Never render trusted HTML from upstream/user content without sanitization.
- Do not store credentials or sensitive game tokens in browser storage.
- UI authorization is presentation only; backend authorization is mandatory.
- Every async screen handles loading, empty, error, success, and stale-response/race behavior.
- User-facing text must use the established localization system; keep locale keys aligned.
- Preserve keyboard access, labels, focus visibility, and usable responsive layouts.

## 13. Code Constraints

- Add `declare(strict_types=1);` to new PHP files and preserve full type declarations where practical.
- Prefer small cohesive methods, early returns, immutable local data, and explicit names.
- Use domain-specific exceptions; do not catch `Throwable` only to suppress it.
- No magic protocol values when an existing config/constant mapping is appropriate.
- No unrelated formatting, mass renames, speculative abstractions, or broad refactors.
- Do not duplicate generated language assets manually when a project command owns them.
- Comments explain non-obvious constraints or decisions, not syntax.
- Conventional Commit prefixes: `feat`, `fix`, `refactor`, `test`, `chore`.

## 14. Forbidden Changes Without Approval

- Destructive shell, Git, database, migration, or filesystem actions.
- Direct edits to `.env`, production configuration, secrets, generated credentials, or live data.
- Live game/production calls or actions against real accounts.
- Disabling or bypassing auth, authorization, CSRF, encryption, redaction, throttling, retry, locking, validation, tests, static analysis, or formatter rules.
- New frameworks, architectural layers, state libraries, ORMs, queue systems, HTTP clients, or test frameworks.
- Protocol “cleanup” not backed by evidence.
- Breaking API/schema changes, irreversible migrations, or data deletion.
- Dependency or runtime upgrades.

## 15. Test Contract

Minimum coverage by change:

- Bug fix: failing regression test first when feasible.
- Service/domain logic: Unit test or focused Feature test.
- Controller/route: validation, authentication, authorization, success, and failure Feature tests.
- Account-scoped behavior: cross-user/cross-account denial test.
- Job/scheduler: retry, duplicate/idempotency, lock, and terminal-failure cases as applicable.
- External protocol: fake HTTP/transport plus realistic fixture; success, malformed response, timeout/error, expired auth.
- Query/schema: migration/constraint behavior and representative query path; verify index intent.
- Frontend: production build plus affected loading/empty/error/success paths; add automated tests if a harness exists.

Required gates when applicable:

```bash
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
```

Do not alter tests merely to accept incorrect behavior. Do not delete, skip, loosen, or snapshot-update assertions without explaining why the expected behavior changed.

## 16. Verification & Self-Correction

For every change:

1. **Discover** — read relevant implementation, tests, schema, configuration, and feature spec.
2. **Plan** — identify invariant, boundary, risk, and smallest change.
3. **Implement** — preserve contracts; add tests with the change.
4. **Verify** — run focused checks, then applicable project gates.
5. **Inspect** — review complete diff, `git diff --check`, generated files, secrets, and unintended scope.
6. **Correct** — fix change-caused failures; repeat verification.
7. **Report** — list changed files, exact checks/results, unverified items, assumptions, and residual risks.

A change is incomplete if evidence is missing, applicable checks fail, or the report claims unexecuted verification.

## 17. Uncertainty Protocol

Stop and ask before proceeding when uncertainty can cause:

- data loss or irreversible migration;
- security/privacy regression;
- live external side effects;
- protocol incompatibility;
- public API breakage;
- architectural/dependency expansion;
- materially different product behavior.

Otherwise, choose the smallest reversible option, state the assumption, and verify it against repository evidence.

## 18. Project Commands

### Environment & Dependency Management
```bash
composer install             # Install PHP dependencies
npm install                  # Install JS dependencies
```

### Testing & Quality Gates
```bash
php artisan test             # Run test suite
./vendor/bin/pint --test     # Check PHP code formatting
./vendor/bin/pint            # Fix PHP code formatting
./vendor/bin/phpstan analyse # Run PHPStan static analysis
```

### Frontend & Assets
```bash
npm run dev                  # Start Vite development server
npm run build                # Build production assets
```

### Database & Cache Operations
```bash
php artisan migrate          # Run database migrations
php artisan db:seed          # Seed the database
php artisan config:clear     # Clear configuration cache
php artisan cache:clear      # Clear application cache
php artisan route:clear      # Clear route cache
```

