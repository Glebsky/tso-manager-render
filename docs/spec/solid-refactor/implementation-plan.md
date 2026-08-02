# SOLID Refactoring Implementation Plan & Roadmap

## 1. Executive Summary & Progress Tracking

| Stage | Domain / Focus Area | Status | Key Deliverables & Artifacts |
| :--- | :--- | :--- | :--- |
| **Stage 1 & 2** | Market Controllers & Domain Services | **COMPLETED** | Split 1360-line `MarketAnalyticsController` into 9 focused REST controllers (`App\Http\Controllers\Market\*`). Created `MarketServerService`, `MarketSettingsService`, `MarketAnalyticsService`, `MarketBulkService`, `ServerPresetProvider`, `LoopArbitrageFinder`. Bound in `MarketServiceProvider`. |
| **Stage 3** | Accounts & Task Planner Strategies | **COMPLETED** | Fixed `ScheduledTaskRequest` prefix bug. Refactored `AccountController` (351 -> 91 lines) with Form Requests (`StoreAccountRequest`, `ExecuteAccountActionRequest`, `UpdateAccountSessionRequest`) and `AccountService`. Extracted `TaskHandlerRegistry` + `TaskActionHandlerInterface` strategies (`StopProductionHandler`, `StartProductionHandler`, `ApplyBuffHandler`, `SendSpecialistHandler`). Extracted `Amf3Encoder`. |
| **Stage 4** | Popular Items & Cache Strategy Config | **COMPLETED** | Added `PopularController`, `MarketPopularRequest`, `PopularItemResource`. Added configurable `cache_strategy` (`'bulk'` \| `'individual'`) in `config/market.php` and `MarketServerService`. |
| **Stage 5** | REST Alignment & JsonResources | **COMPLETED** | Created `AccountResource`, `ScheduledTaskResource`, `BotLogResource`. Refactored `AccountController`, `ScheduledTaskController`, `LogController`, `DashboardController`, and `SettingsController` (`UpdateSettingsRequest`). Enforced Constructor DI across all non-market controllers. |
| **Stage 6** | Market Synchronization Decomposition (`MarketSyncService`) | **COMPLETED** | Decomposed monolithic 372-line `MarketSyncService` into `MarketOfferFetcher`, `MarketOfferParser`, `MarketOfferPersister`, `MarketSyncLogger`, and a high-level `MarketSyncService` orchestrator using Constructor DI. |
| **Stage 7** | Game Zone Parser (`ZoneParserService`) | **COMPLETED** | Decomposed `ZoneParserService` into `ZoneAmfExecutor`, `ZoneResourceCategorizer`, and lightweight `ZoneParserService` orchestrator using Constructor DI. |
| **Stage 8** | Scheduled Task Validation & Form Requests | **COMPLETED** | Extended `ScheduledTaskRequest`, `StoreScheduledTaskRequest`, and `UpdateScheduledTaskRequest` to cover all task payload types (`sequence`, `stop_production`, `start_production`, `send_geologist`, `send_explorer`, `send_specialist`, `apply_buff`). Added `ScheduledTaskRequestTest` unit test suite. |
| **Stage 9** | Scheduler Engine & Console Commands | **PENDING** | Slim `RunSchedulerCommand` (239 lines) and `ExecuteScheduledTasks` (124 lines) by extracting `TaskSchedulerEngine`. |
| **Stage 10** | Account Sync & Session Pipeline | **PENDING** | Decompose `AccountSyncService` (172 lines) into pure auth, protocol, parsing, and persistence pipeline steps using Constructor DI. |

---

## 2. Detailed Breakdown of Completed Milestones

### 2.1 Stage 1 & 2: Market Architecture (Delivered)
- **Eliminated Monolith**: `MarketAnalyticsController.php` (1360 lines) was completely decomposed into single-action micro-controllers adhering to REST conventions.
- **Interfaces & Service Provider**: Bound `ResourceNameResolver` and `ArbitrageFinder` inside `MarketServiceProvider`.

### 2.2 Stage 3: Account & Task Execution Strategy (Delivered)
- **Slim Controllers**: `AccountController` method size <= 10 lines, validation extracted to Form Requests.
- **Strategy & Registry**: `TaskExecutionService` delegates single action execution to container-bound `TaskHandlerRegistry`.

### 2.3 Stage 4: Popular Items & Config-driven Cache Strategy (Delivered)
- **Popular Endpoints**: Added `PopularController`, `MarketPopularRequest`, and `PopularItemResource`.

### 2.4 Stage 5: REST Alignment & JsonResources Layer (Delivered)
- **JsonResources Integration**: Created `AccountResource`, `ScheduledTaskResource`, `BotLogResource`.
- **System-Wide Controller Refactoring**:
  - `AccountController`: Refactored to use `AccountResource` and Constructor DI for `AccountSyncService`.
  - `ScheduledTaskController`: Refactored to use `ScheduledTaskResource` and `AccountResource`.
  - `LogController`: Refactored to use `BotLogResource` and `AccountResource`.
  - `DashboardController`: Refactored to use `AccountResource` and `BotLogResource`.
  - `SettingsController`: Extracted `UpdateSettingsRequest` and injected `SystemLogCleanupService` via Constructor DI.

### 2.5 Stage 6: Market Synchronization Decomposition (Delivered)
- **Extracted Sub-Services (`App\Services\Market\Sync\*`)**:
  - `MarketOfferFetcher`: Network fetching of market offers via `TsoAmfService` and Python parser execution.
  - `MarketOfferParser`: Domain parsing of raw offer strings into structured offer and history arrays.
  - `MarketOfferPersister`: Batch database transaction, active offer replacement, and history deduplication.
  - `MarketSyncLogger`: System log writing and `MarketSyncLog` / `BotLog` database recording.
- **Orchestrator**: `MarketSyncService` reduced from 372 lines to a clean 80-line orchestrator delegating all work via Constructor DI.

### 2.6 Stage 7: Game Zone Parser Refactoring (Delivered)
- **Extracted Sub-Services (`App\Services\Zone\*`)**:
  - `ZoneAmfExecutor`: Handles Python binary execution (`parse_zone.py`) and temporary file I/O safely.
  - `ZoneResourceCategorizer`: Pure domain categorizer mapping game resource names to warehouse tab categories (`WarehouseTab1` - `WarehouseTab8`).
- **Orchestrator**: `ZoneParserService` converted into a lightweight orchestrator with strict types and Constructor Property Promotion DI.

### 2.7 Stage 8: Scheduled Task Form Requests & Validation (Delivered)
- **Comprehensive Payload Validation**:
  - Extended `ScheduledTaskRequest` with `buildingRules()` (`payload.grid`), `specialistRules()` (`payload.task_type`, `payload.sub_task_id`, `payload.unique_id1`, `payload.unique_id2`, `payload.specialist_type`, `payload.search_type`), `sequenceRules()` (step payload validation), and `buffRules()` (`BuffPayloadValidator`).
  - Implemented `StoreScheduledTaskRequest` for strict task creation.
  - Implemented `UpdateScheduledTaskRequest` with flexible `sometimes` rules for partial updates.
- **Unit Test Suite**: Added [ScheduledTaskRequestTest.php](file:///C:/OSPanel/domains/tso_client/admin/tests/Unit/ScheduledTaskRequestTest.php).

---

## 3. Detailed Backlog & Roadmap for Remaining Refactoring

### Stage 9: Scheduler Engine & Console Command Slimming
- **Current State**: `RunSchedulerCommand.php` (239 lines) and `ExecuteScheduledTasks.php` (124 lines) contain inline lock management, task queries, and dispatch loops.
- **Refactoring Strategy**:
  - Extract `App\Services\Tasks\TaskSchedulerEngine`: Handles atomic task reservation, interval checking, and queue dispatching.
  - Slim `RunSchedulerCommand` and `ExecuteScheduledTasks` to under 30 lines.

### Stage 10: Account Sync Pipeline Refactoring (`AccountSyncService`)
- **Current State**: `AccountSyncService.php` (172 lines) mixes authentication, zone fetching, parsing, and model updates.
- **Refactoring Strategy**:
  - Refactor into a clean pipeline: Auth (`TsoAuthService`) -> Fetch Zone (`TsoAmfService`) -> Parse Zone (`ZoneParserService`) -> Account Updates (`AccountService`).
