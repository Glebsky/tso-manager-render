# SOLID Refactoring Implementation Plan & Backlog

## 1. Overview & Status

| Stage | Focus Area | Status | Deliverables |
| :--- | :--- | :--- | :--- |
| **Stage 1 & 2** | Market Controllers & Services | **COMPLETED** | Split 1360-line `MarketAnalyticsController` into 9 focused controllers under `App\Http\Controllers\Market\*`, bound in `MarketServiceProvider`. |
| **Stage 3** | Accounts & Task Handlers | **COMPLETED** | Refactored `AccountController` into Form Requests + `AccountService`. Extracted `TaskHandlerRegistry` + `TaskActionHandlerInterface` strategies (`StopProductionHandler`, `StartProductionHandler`, `ApplyBuffHandler`, `SendSpecialistHandler`). Extracted `Amf3Encoder`. |
| **Stage 4** | Market Sync & Infrastructure | **PENDING** | Decompose `MarketSyncService` (310 lines) and `MarketServerService` (302 lines). |
| **Stage 5** | Zone Parser & Game Error Domain | **PENDING** | Decompose `ZoneParserService` (211 lines) into dedicated AMF AST Nodes/Parsers. |
| **Stage 6** | Console & Scheduler Command Slimming | **PENDING** | Slim `RunSchedulerCommand` (239 lines) and `ExecuteScheduledTasks` (124 lines). |

---

## 2. Completed Milestones (Stage 3 Detailed Audit)

### 2.1 Fixed Regression & Validation Alignment
- **Fixed `ScheduledTaskRequest.php`**: Corrected array key iteration in `buffRules()` (`$prefix => $inputKey`), ensuring exact rule mapping for `payload.grid`, `payload.unique_id1`, etc.
- **Removed Unused File**: Deleted `app/Http/Controllers/MarketAnalyticsController.php` (1183 lines) and updated `phpstan.neon`.

### 2.2 Controllers & Service Provider Registration
- **Account Controllers**: Slimmed `AccountController.php` down to 91 lines using `StoreAccountRequest`, `ExecuteAccountActionRequest`, `UpdateAccountSessionRequest`, and `AccountService`.
- **Task Providers**: Created `TaskServiceProvider` and registered in `config/app.php`.
- **TSO Providers**: Created `TsoServiceProvider` and registered in `config/app.php`.

---

## 3. Remaining Backlog for Complete Refactoring (Stages 4–6)

### Stage 4: Market Sync & Infrastructure Decomposition
- **Target**: `MarketSyncService.php` (310 lines).
- **Problem**: Holds network fetch calls, offer chunking, database transactions, history logging, and versioning.
- **Proposed Extraction**:
  - `App\Services\Market\Sync\MarketOfferFetcher`: Network retrieval of market offers via AMF.
  - `App\Services\Market\Sync\MarketOfferPersister`: Batch database UPSERT and history record creation within database transactions.
  - `App\Services\Market\Sync\MarketSyncOrchestrator`: Orchestrating fetcher and persister.

### Stage 5: Game Zone Parser Refactoring
- **Target**: `ZoneParserService.php` (211 lines).
- **Problem**: Combines raw AMF array traversal, building extraction, friend list parsing, and error code parsing.
- **Proposed Extraction**:
  - `App\Services\Zone\BuildingExtractor`: Pure parser for zone building list.
  - `App\Services\Zone\FriendListExtractor`: Pure parser for player friend lists.

### Stage 6: Console Command Refactoring
- **Target**: `RunSchedulerCommand.php` (239 lines) & `ExecuteScheduledTasks.php` (124 lines).
- **Problem**: Inline lock management, database queries, and task dispatch loops.
- **Proposed Extraction**:
  - Move scheduler reservation and job dispatch into `TaskSchedulerOrchestrator` service.
  - Keep CLI commands strictly as input/output triggers.
