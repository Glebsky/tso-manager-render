# Architecture Hardening Specification (Phase 2)

Spec-Driven Development (SDD) package for the second architectural pass over the
admin application. Phase 1 lives in `docs/spec/solid-refactor/` and delivered the
initial layering (controllers -> services -> models, Strategy/Registry for task
actions, Form Requests, JsonResources). This phase closes the gaps that Phase 1
left open and the items still unchecked in `docs/foundbugs.md`.

## Documents

| Document | Purpose |
| :--- | :--- |
| `audit.md` | Evidence-based findings with file paths, line counts and the violated rule. |
| `decisions.md` | **ADR-001 … ADR-008 — owner decisions of 2026-08-05. Read first: they override earlier wording.** |
| `requirements.md` | Normative requirements (FR/NFR) with acceptance criteria. |
| `design.md` | Target architecture, new types, patterns and directory layout. |
| `implementation-plan.md` | Ordered, independently shippable work packages with gates. |
| `verification.md` | Verification matrix and gate protocol; filled in during execution. |

## Decisions in force

| ADR | Decision |
| :--- | :--- |
| 001 | Single-operator application. No `user_id`, no policies, no ACL/RBAC — the invariant is enforced by first-user-only registration and covered by tests instead. |
| 002 | Runtime upgrade approved: PHP 8.4 + latest stable Laravel, in three separate packages (PHP → L11 → L12+). |
| 003 | API contract = `JsonResource` + HTTP status codes + centrally rendered exceptions. `ApiResponder` retired; UI text moves to frontend i18n. |
| 004 | `larastan` added, PHPStan level 6 over `app`/`database`/`routes`/`tests`, blanket ignore removed, shrinking baseline. |
| 005 | Pagination for tasks and logs only; accounts stay a full collection (but still a `ResourceCollection` with `meta`). |
| 006 | Test certificates deleted and purged from git history. |
| 007 | Credential encryption without a maintenance window; lossy fallback acceptable (never was in production). |
| 008 | Pragmatic REST inside Laravel conventions; action sub-resources stay `POST /{resource}/{id}/{action}`. |

## Scope

In scope: `app/`, `resources/js/`, `resources/views/`, `routes/`, `phpstan.neon`,
`composer.json`, `Dockerfile`, `docker/`, `AGENTS.md`, `docs/spec/constitution.md`.

Out of scope: protocol contract changes, breaking schema changes, `.env` edits,
new frontend frameworks or state libraries.

## Priority summary (revised after ADR-001 / ADR-002)

| # | Theme | Severity |
| :--- | :--- | :--- |
| P0-1 | TLS material committed to the repository | Critical (security) |
| P0-2 | Unsupported runtime: PHP 8.1 and Laravel 10 are both past end of support | Critical (security) |
| P0-3 | Game credentials and auth tokens stored unencrypted | Critical (security) |
| P0-4 | Exception messages leaked to API responses (`AccountController::sync`) | High (security) |
| P0-5 | Single-operator invariant is implicit — not asserted by any test | High |
| P1-1 | `TaskExecutionService` (547 lines): finalization duplicated 5×, two competing sequence engines | High |
| P1-2 | Magic strings for statuses / task types / schedule types / log levels | High |
| P1-3 | Three competing response styles; hardcoded English in PHP; `server_time` ×4 | High |
| P1-4 | PHPStan gate is decorative: level 0, 3 files, blanket ignore | High |
| P2-1 | Fat `Account` model: triple JSON decode + per-row query in `$appends` (N+1) | Medium |
| P2-2 | `tasks` and `logs` endpoints unbounded | Medium |
| P2-3 | `TsoAmfService` (525): 4 classes per file, untyped VOs, transport+protocol mixed, no contract | Medium |
| P2-4 | Session/cookie encapsulation leak (`@unlink($authService->getCookieFile(...))`) | Medium |
| P3-1 | Frontend: 7 333 lines in 4 views, Options API in `Tasks.vue`, 28 axios call sites, duplicated analytics | Medium |
| P3-2 | Dead legacy Blade UI (~1 500 lines) unreachable from any route | Low |
| P3-3 | Repo hygiene: tracked `.phpunit.result.cache`, Windows-only `make install`, `file:///C:/...` doc links | Low |
