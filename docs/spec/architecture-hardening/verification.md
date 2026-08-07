# Architecture Hardening — Verification Protocol

This document is filled in **during** execution. A work package is not done until
its row carries real, executed command output. Per constitution §16, a change is
incomplete if evidence is missing or a report claims unexecuted verification.

---

## 1. Gate commands (run per work package)

```bash
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
```

Focused runs are allowed while iterating (`php artisan test --compact --filter=...`),
but the full four gates must pass before the package is declared done.

---

## 2. Baseline (as inspected, before any change)

| Metric | Value |
| :--- | :--- |
| PHP/Vue/JS lines (app, resources, routes, config, database) | 26 984 |
| Largest backend file | `app/Services/TaskExecutionService.php` — 547 |
| Largest frontend file | `resources/js/views/Tasks.vue` — 2 691 |
| Top 4 views combined | 7 333 lines (27 % of the codebase) |
| Hand-built `response()->json` in controllers | 22 |
| `axios.` call sites in views/components | 28 |
| PHPStan scope / level | 3 files / level 0 |
| Test files | 28 (14 Feature, 11 Unit, 3 fixtures) |
| Policies registered | 0 |
| Tracked `*.pem` files | 2 |
| Unreachable Blade files | ~13 (~1 500 lines) |

---

## 3. Verification matrix

| WP | Gate evidence | Targeted assertions | Manual check | Status |
| :--- | :--- | :--- | :--- | :--- |
| WP-1 | 4 gates | `git ls-files` has no `*.pem` | `docker compose config`, `make prod-up`, HTTPS responds | DONE |
| WP-2' | 4 gates | single-operator invariant test (registration, 401 routes, public market keys) | login & registration flow | DONE |
| WP-U1 | 4 gates | composer.json ^8.3, Dockerfile php:8.3, zero deprecation warnings | suite execution on PHP 8.3 | DONE |
| WP-U2 | 4 gates | composer.json ^11.0, sanctum ^4.0, bootstrap/app.php structure, zero Kernel files | login, SPA boot, task run, market sync | DONE |
| WP-U3 | 4 gates | composer.json ^8.4, Dockerfile php:8.4, laravel framework ^12.0 | test suite, pint, phpstan, vite build | DONE |
| WP-3 | 4 gates | raw column ≠ plaintext; model round-trip; no secret in logs | migration up/down on a DB copy | TODO |
| WP-4 | 4 gates | each domain exception → declared status + localized message; no upstream text | trigger a sync failure in the UI | TODO |
| WP-5 | phpstan at target level | baseline line count recorded below | — | TODO |
| WP-6 | 4 gates | enum values equal the stored strings; existing suite unchanged | task list/planner renders statuses | TODO |
| WP-7 | 4 gates | characterization suite green; finalization block appears once (grep) | manual run + sequence with delays, paused task, `once` task | TODO |
| WP-8 | 4 gates | zero `response()->json` in controllers; locale key sets identical; pagination bounds | Accounts / Tasks / Logs screens | TODO |
| WP-9 | 4 gates | query count constant for 10 accounts; snapshot handles malformed JSON | dashboard + account list | TODO |
| WP-10 | 4 gates | AMF payload snapshot byte-identical; transport faked | one real sync against a test account (owner-approved) | TODO |
| WP-11 | 4 gates | grep proves zero Blade references | `/admin/login`, SPA boot | DONE |
| WP-12 | `npm run build` + 4 gates | no `axios.` in views/components | loading/empty/error/stale on 6 screens | TODO |
| WP-13 | `npm run build` + 4 gates | — | task planner regression list (§4) | TODO |
| WP-14 | `npm run build` + 4 gates | combined view lines − ≥ 60 % | admin + public analytics parity | TODO |
| WP-15 | 4 gates | stuck-manual-run reproduced then fixed; payload keeps `name` + `grid` | manual run of a long sequence | TODO |

---

## 4. Frontend regression checklist (WP-12 – WP-14)

These behaviors are already marked done in `docs/foundbugs.md` and must not regress:

- animated gradient status border on dashboard account cards;
- spinner on the Sync button and blur+spinner overlays (including the market
  resources panel);
- no layout jump on navigation (cache + skeleton/spinner);
- specialists grid 4-per-row, no white frames;
- toasts on top of everything, top-right;
- lazy-loaded images; responsive/mobile layout;
- Recent Activity auto-refresh;
- buff label shows the resource name (no `{1,RES}` residue);
- generals/explorers/geologists counted from the specialists tab;
- collapsible «Current Active Market Listings» and «Most Popular Items»; Player
  column after Buying Resource; copy-pair link button; server switcher;
- task planner: building name **and** grid everywhere, error pinned to the failing
  action, delay preserved on add, multi-select buildings/specialists, zone-first
  buff flow, current + server time, one-off date/time and interval schedules;
- localization complete in en / ru / uk, including the Ukraine support notice.

---

## 5. PHPStan debt ledger (WP-5 onward)

| Date | Level | Paths | Baseline lines | Note |
| :--- | :--- | :--- | :--- | :--- |
| 2026-08-07 | 6 | app, database, routes, tests | 776 lines (144 errors baseline) | initial baseline with larastan 3.10 and level 6 |

The baseline must shrink or stay flat in every subsequent package; it may never grow.

---

## 6. Reporting template (per work package)

```text
WP-<n> <title>
Changed files: <list>
Commands executed: <exact commands + results>
Tests added/updated: <list>
Contract changes: <none | explicit list>
Unverified items: <list or none>
Assumptions: <list>
Residual risk: <list>
```
