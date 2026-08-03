# SOLID & Clean Layered Architecture Refactoring Specification

This directory contains the Spec-Driven Development (SDD) documentation for the project-wide SOLID refactoring.

## Specification Documents

- [Requirements](file:///C:/OSPanel/domains/tso_client/admin/docs/spec/solid-refactor/requirements.md) — Core architectural principles, SOLID rules, RESTful API design principles, mandatory Constructor DI, JsonResources standard, PHP 8.1 / Laravel 10 constraints, and API contracts.
- [Architecture Design](file:///C:/OSPanel/domains/tso_client/admin/docs/spec/solid-refactor/design.md) — Layered architecture diagrams, Strategy/Registry patterns, REST principles, Form Requests, JsonResources, and directory layout.
- [Implementation Plan & Roadmap](file:///C:/OSPanel/domains/tso_client/admin/docs/spec/solid-refactor/implementation-plan.md) — Detailed audit of completed stages and remaining backlog for full refactoring.
- [Verification Report](file:///C:/OSPanel/domains/tso_client/admin/docs/spec/solid-refactor/verification.md) — Quality gates, test execution results, static analysis, and build verification.

## Summary of Delivered Stages

1. **Stage 1 & 2 (Market Domain Decomposition)**:
   - Split 1360-line `MarketAnalyticsController` into 9 focused REST controllers under `App\Http\Controllers\Market\*`.
   - Extracted market services (`MarketServerService`, `MarketSettingsService`, `MarketAnalyticsService`, `MarketBulkService`, `ServerPresetProvider`, `PopularItemService`) behind `ResourceNameResolver` and `ArbitrageFinder` contracts.
   - Bound abstractions in `App\Providers\MarketServiceProvider` and registered in `config/app.php`.

2. **Stage 3 (Account & Task Planner Domain)**:
   - Fixed validation bug in `ScheduledTaskRequest.php` key prefix iteration (`$prefix => $inputKey`).
   - Removed dead legacy file `app/Http/Controllers/MarketAnalyticsController.php` (1183 lines) and updated `phpstan.neon`.
   - Refactored `AccountController.php` (351 -> 91 lines) with Form Requests (`StoreAccountRequest`, `ExecuteAccountActionRequest`, `UpdateAccountSessionRequest`) and `AccountService`.
   - Decomposed `TaskExecutionService.php` using Strategy Pattern (`TaskActionHandlerInterface`) and container-bound `TaskHandlerRegistry` with `StopProductionHandler`, `StartProductionHandler`, `ApplyBuffHandler`, and `SendSpecialistHandler`.
   - Extracted `Amf3Encoder.php` for AMF3 protocol serialization.
   - Registered `TaskServiceProvider` and `TsoServiceProvider` in `config/app.php`.

3. **Stage 4 (Popular Items & App Config Strategy)**:
   - Added `PopularController.php`, `MarketPopularRequest.php`, and `PopularItemResource.php`.
   - Added configurable `cache_strategy` (`'bulk'` \| `'individual'`) in `config/market.php` and `MarketServerService`.

4. **Stage 5 (REST Alignment & JsonResources)**:
   - Created `AccountResource`, `ScheduledTaskResource`, `BotLogResource`.
   - Refactored `AccountController`, `ScheduledTaskController`, `LogController`, `DashboardController`, `SettingsController`.

5. **Stage 6 (Market Synchronization Decomposition)**:
   - Decomposed 372-line `MarketSyncService` into `MarketOfferFetcher`, `MarketOfferParser`, `MarketOfferPersister`, `MarketSyncLogger`, and a high-level `MarketSyncService` orchestrator using Constructor DI.

6. **Stage 7 (Game Zone Parser Refactoring)**:
   - Decomposed `ZoneParserService` into `ZoneAmfExecutor`, `ZoneResourceCategorizer`, and a high-level `ZoneParserService` orchestrator with strict types and Constructor Property Promotion DI.

7. **Stage 8 (Scheduled Task Form Requests & Validation)**:
   - Extended `ScheduledTaskRequest` with strict validation rules for building production, specialist tasks, sequences, and buff payloads.
   - Implemented `StoreScheduledTaskRequest` and `UpdateScheduledTaskRequest` Form Requests.
   - Added `ScheduledTaskRequestTest` unit test suite.

8. **Stage 9 (Scheduler Engine & Console Commands)**:
   - Extracted `TaskSchedulerEngine` domain service (`App\Services\Tasks\TaskSchedulerEngine`).
   - Slimmed down `RunSchedulerCommand` (293 -> 60 lines) and `ExecuteScheduledTasks` (158 -> 48 lines) into ultra-clean CLI wrappers.
   - Added `TaskSchedulerEngineTest` unit test suite.

## Remaining Backlog (Prioritized Roadmap)

1. **Stage 10 — Account Sync Pipeline Refactoring**:
   - Decompose `AccountSyncService` into pure auth, protocol, parsing, and persistence pipeline steps using Constructor DI.
