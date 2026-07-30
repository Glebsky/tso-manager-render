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

## Remaining Backlog (Prioritized Roadmap)

1. **Stage 5 — REST Alignment & JsonResources**:
   - Audit API routes for REST Noun/Verb adherence. Add `AccountResource` and `ScheduledTaskResource` for unified JSON responses across endpoints.
2. **Stage 6 — `MarketSyncService` Decomposition**:
   - Split network fetching (`MarketOfferFetcher`) from batch persistence (`MarketOfferPersister`).
3. **Stage 7 — `ZoneParserService` Refactoring**:
   - Extract pure extractors (`BuildingGridParser`, `FriendListParser`) from raw AMF parsing.
4. **Stage 8 — Scheduled Task Form Requests**:
   - Fully implement `StoreScheduledTaskRequest` and `UpdateScheduledTaskRequest` with strict rules for sequence steps, buff payloads, and specialist searches.
5. **Stage 9 — Console Command & Scheduler Engine**:
   - Move scheduler reservation and queue dispatch logic out of `RunSchedulerCommand` into `TaskSchedulerEngine`.
6. **Stage 10 — Account Sync Pipeline Refactoring**:
   - Decompose `AccountSyncService` into pure auth, protocol, parsing, and persistence pipeline steps using Constructor DI.
