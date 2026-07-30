# SOLID Refactoring Architectural Design

## 1. System Architecture Diagram

```mermaid
graph TD
    Client[Vue 3 SPA / Public Portal] -->|HTTP / API| Controllers[Http Controllers]
    Controllers -->|Form Requests| FormRequests[Form Requests Validation]
    Controllers -->|Json Resources| JsonResources[Json Resources Serialization]
    Controllers -->|Delegate| Services[Domain Services]
    
    subgraph "Domain Layer"
        Services -->|Strategy Call| TaskRegistry[TaskHandlerRegistry]
        TaskRegistry -->|Resolve| TaskHandlers[Task Action Handlers]
        Services -->|Protocol| AMFClient[Amf3Encoder / TsoAmfService]
        Services -->|Auth| AuthService[TsoAuthService]
        Services -->|Popular| PopularService[PopularItemService]
    end

    subgraph "Data & Persistence Layer"
        Services -->|Eloquent| Models[Account / ScheduledTask / MarketOffer / MarketHistory]
        Services -->|Cache| Redis[Cache & Locks]
    end
```

---

## 2. Component Design & Abstractions

### 2.1 Task Planner Engine
- **Contract**: `App\Services\Tasks\Contracts\TaskActionHandlerInterface`
- **Registry**: `TaskHandlerRegistry` receives all handlers lazily via container resolution to maintain compatibility with test mocking frameworks (`Mockery`).
- **Service Providers**: Registered in `App\Providers\TaskServiceProvider`.

### 2.2 Account Management Boundary
- **Form Requests**: `StoreAccountRequest`, `ExecuteAccountActionRequest`, `UpdateAccountSessionRequest`.
- **Domain Service**: `AccountService` encapsulates cookie cleanup, session updates, action delegation, and friend zone caching/stale fallback.
- **Slim Controller**: `AccountController` contains methods strictly under 10 lines.

### 2.3 Market Domain Boundary
- **Form Requests**: `StoreMarketServerRequest`, `UpdateMarketServerRequest`, `UpdateMarketSettingsRequest`, `MarketPopularRequest`.
- **Json Resources**: `MarketOfferResource`, `MarketSyncLogResource`, `PublicServerResource`, `PopularItemResource`.
- **Controllers**: `ServerController`, `PublicServerController`, `CatalogController`, `AnalyticsController`, `ArbitrageController`, `BulkController`, `SyncLogController`, `VersionController`, `SettingsController`, `PopularController`.

### 2.4 AMF & Protocol Isolation
- **Encoder**: `App\Services\Amf\Amf3Encoder` handles binary serialization into AMF3.
- **Service Provider**: `App\Providers\TsoServiceProvider` binds `TsoAuthService` and `TsoAmfService`.

---

## 3. Directory Layout Compliance

All new classes must stay within existing top-level application structure:

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── AccountController.php
│   │   ├── Market/
│   │   │   ├── AnalyticsController.php
│   │   │   ├── ArbitrageController.php
│   │   │   ├── BulkController.php
│   │   │   ├── CatalogController.php
│   │   │   ├── PopularController.php
│   │   │   ├── PublicServerController.php
│   │   │   ├── ServerController.php
│   │   │   ├── SettingsController.php
│   │   │   ├── SyncLogController.php
│   │   │   └── VersionController.php
│   │   └── ScheduledTaskController.php
│   ├── Requests/
│   │   ├── Account/
│   │   ├── Market/
│   │   └── Tasks/
│   └── Resources/
│       ├── MarketOfferResource.php
│       ├── MarketSyncLogResource.php
│       ├── PopularItemResource.php
│       └── PublicServerResource.php
├── Providers/
│   ├── MarketServiceProvider.php
│   ├── TaskServiceProvider.php
│   └── TsoServiceProvider.php
├── Services/
│   ├── AccountService.php
│   ├── Amf/
│   │   └── Amf3Encoder.php
│   ├── Market/
│   │   ├── Contracts/
│   │   ├── Support/
│   │   ├── MarketAnalyticsService.php
│   │   ├── MarketBulkService.php
│   │   ├── MarketCatalogService.php
│   │   ├── MarketOfferQueryService.php
│   │   ├── MarketServerService.php
│   │   ├── MarketSettingsService.php
│   │   ├── PopularItemService.php
│   │   └── ServerPresetProvider.php
│   ├── Tasks/
│   │   ├── Contracts/
│   │   ├── Handlers/
│   │   └── TaskHandlerRegistry.php
│   ├── TaskExecutionService.php
│   ├── TsoAmfService.php
│   └── TsoAuthService.php
```
