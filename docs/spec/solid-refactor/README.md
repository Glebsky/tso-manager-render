# SOLID & Clean Layered Architecture Refactoring Specification

This directory contains the Spec-Driven Development (SDD) documentation for the project-wide SOLID refactoring.

## Specification Documents

- [Requirements](file:///C:/OSPanel/domains/tso_client/admin/docs/spec/solid-refactor/requirements.md) — Core architectural principles, SOLID rules, PHP 8.1 / Laravel 10 constraints, and API contracts.
- [Architecture Design](file:///C:/OSPanel/domains/tso_client/admin/docs/spec/solid-refactor/design.md) — Layered architecture diagrams, Strategy/Registry patterns, and directory layouts.
- [Implementation Plan & Backlog](file:///C:/OSPanel/domains/tso_client/admin/docs/spec/solid-refactor/implementation-plan.md) — Detailed audit of completed stages and remaining backlog for full refactoring.
- [Verification Report](file:///C:/OSPanel/domains/tso_client/admin/docs/spec/solid-refactor/verification.md) — Quality gates, test execution results, static analysis, and build verification.

## Summary of Delivered Stages

1. **Stage 1 & 2 (Market Domain)**:
   - Split 1360-line `MarketAnalyticsController` into 9 focused controllers under `App\Http\Controllers\Market\*`.
   - Extracted market services (`MarketServerService`, `MarketSettingsService`, `MarketAnalyticsService`, `MarketBulkService`, `ServerPresetProvider`) behind `ResourceNameResolver` and `ArbitrageFinder` contracts.
   - Bound abstractions in `App\Providers\MarketServiceProvider` and registered in `config/app.php`.

2. **Stage 3 (Account & Task Planner Domain)**:
   - Fixed validation bug in `ScheduledTaskRequest.php` key prefix iteration (`$prefix => $inputKey`).
   - Removed dead legacy file `app/Http/Controllers/MarketAnalyticsController.php` (1183 lines) and updated `phpstan.neon`.
   - Refactored `AccountController.php` (351 -> 91 lines) with Form Requests (`StoreAccountRequest`, `ExecuteAccountActionRequest`, `UpdateAccountSessionRequest`) and `AccountService`.
   - Decomposed `TaskExecutionService.php` using Strategy Pattern (`TaskActionHandlerInterface`) and container-bound `TaskHandlerRegistry` with `StopProductionHandler`, `StartProductionHandler`, `ApplyBuffHandler`, and `SendSpecialistHandler`.
   - Extracted `Amf3Encoder.php` for AMF3 protocol serialization.
   - Registered `TaskServiceProvider` and `TsoServiceProvider` in `config/app.php`.

## Remaining Backlog (Next Priority Tasks)

1. **Stage 4 — `MarketSyncService` Decomposition**:
   - Split network fetching (`MarketOfferFetcher`) from batch persistence (`MarketOfferPersister`).
2. **Stage 5 — `ZoneParserService` Refactoring**:
   - Extract pure extractors (`BuildingExtractor`, `FriendListExtractor`) from raw AMF parsing.
3. **Stage 6 — Console Command Slimming**:
   - Move scheduler reservation and queue dispatch logic out of `RunSchedulerCommand` into `TaskSchedulerOrchestrator`.
