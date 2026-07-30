# SOLID refactor: architecture plan and progress

## 1. Why

The audit of the current code base found the following systemic problems:

| Problem | Evidence |
| --- | --- |
| Business logic lives in controllers | `MarketAnalyticsController` = 1360 lines, `ScheduledTaskController` = 371, `AccountController` = 350 |
| God classes | one controller owns server CRUD, verification, sync, settings, catalogue, analytics, arbitrage, bulk and logs |
| No dependency injection discipline | manual property assignment in constructors, `Setting::get()` / `MarketOffer::` static calls sprinkled through HTTP code |
| Laravel features unused | zero Form Requests, exactly one API Resource (`PublicServerResource`), inline `$request->validate()` everywhere |
| Heavy duplication | offer to array mapping x3, catalogue union query x4, driver specific `time_bucket` SQL x2, period `match()` x3, `BotLog::create()` x6, a 50 line validation block duplicated between `store()` and `update()` |
| Magic values | `now()->subHours(6)`, `'ru'`, cache TTLs, server presets all hard coded |
| Latent risk | granularity string interpolated into `selectRaw()` without a whitelist |

## 2. Target architecture

Direction of dependencies (per constitution section 5):

```
HTTP / Console / Jobs  ->  Services  ->  Models
```

* **Controllers**: authorize, validate (Form Request), call one service, return a Resource. No queries, no `match()`, no business rules.
* **Services**: one responsibility each. No service may exceed a single cohesive concern.
* **Contracts**: introduced only at genuinely replaceable boundaries (name resolution, SQL dialect, arbitrage strategy) - not as a repository wrapper over Eloquent.
* **Value objects**: replace loose `(period, dateFilter, groupBy)` variable triples.

### SOLID mapping

| Principle | Applied as |
| --- | --- |
| SRP | `MarketAnalyticsController` split into 10 focused services + 10 thin controllers |
| OCP | `TimeBucketExpression` strategy per DB driver; adding an engine adds a class instead of editing three `if` chains. Same for account action handlers |
| LSP | every `TimeBucketExpression` accepts the same whitelisted `TimeGranularity` enum and returns a valid SQL expression |
| ISP | narrow contracts: `ResourceNameResolver` (1 method), `ArbitrageFinder` (1 method), `TaskActivityLogger` (1 method) instead of fat service interfaces |
| DIP | consumers depend on contracts and injected scalars from `config/market.php`; wiring lives in `MarketServiceProvider` |

## 3. Stage 1 - delivered in this change set

The shared foundation and every deduplicated read path. All of it is **additive**: no existing class was modified, so current behaviour is unchanged and the app keeps running exactly as before while the new layer is adopted.

### Configuration

* `config/market.php` - offer lifetime, default server, cache TTLs, bulk settings, sync interval, popular items limit, arbitrage switch, and the 7 server presets that used to be a private controller method.

### Shared HTTP support

* `app/Support/Http/ApiResponder.php` - the single owner of the JSON envelope (`data` / `success` / `failure` / `validationError`), replacing ~60 hand rolled `response()->json([...])` blocks.

### Value objects and abstractions

* `app/Services/Market/Support/TimeGranularity.php` - string enum (`hour|day|week|month`) that whitelists the value before it can ever reach `selectRaw()`, and owns the frontend display format.
* `app/Services/Market/Support/MarketPeriod.php` - immutable VO (`key`, `since`, `granularity`).
* `app/Services/Market/Support/PeriodResolver.php` - the single place translating the client `period` parameter and refining granularity from the real data span.
* `app/Services/Market/Support/TimeBucket/TimeBucketExpression.php` + `PostgresTimeBucketExpression`, `MySqlTimeBucketExpression`, `SqliteTimeBucketExpression`, `TimeBucketExpressionFactory` - the dialect strategies replacing duplicated driver branching.
* `app/Services/Market/Contracts/ResourceNameResolver.php` + `app/Services/Market/GameResourceNameResolver.php` - replaces the private `resourceName()` helper used from 6 call sites.
* `app/Services/Market/Contracts/ArbitrageFinder.php`.

### Domain services

* `app/Services/Market/MarketOfferQueryService.php` - all active offer reads; the `subHours(6)` magic literal is now injected configuration.
* `app/Services/Market/MarketCatalogService.php` - goods / targets / targets map; the union + unique + natural sort pipeline exists once instead of four times.
* `app/Services/Market/PopularItemService.php` - most traded goods per period.
* `app/Services/Market/MarketHistoryAggregator.php` - every aggregate read over `market_history`: price stats, period totals, bucketed series, per pair series, latest price per pair.
* `app/Services/Market/Arbitrage/LoopArbitrageFinder.php` - the 2 step and 3 step barter loop detector, lifted out of the controller.

### Presentation

* `app/Http/Resources/MarketOfferResource.php` - one serializer for an offer, replacing the identical 18 line array literal that appeared in analytics, bulk and arbitrage.

### Wiring and tests

* `app/Providers/MarketServiceProvider.php` (registered in `config/app.php`) - binds the contracts and the scalar config values; everything else is plain autowiring.
* `tests/Unit/Market/PeriodResolverTest.php`, `tests/Unit/Market/TimeBucketExpressionTest.php` - pure unit tests, no DB required.

### Behaviour preservation note

`LoopArbitrageFinder::find()` was produced by a **mechanical extraction** of the former `MarketAnalyticsController::buildArbitrageData()` body (original lines 1078-1358), with only these rewires, each asserted during extraction:

* `$this->resourceName(...)` -> `$this->names->resolve(...)` (2 occurrences)
* `now()->subHours(6)` -> `$this->offers->activeSince()` (1 occurrence)
* `array_slice($loops, 0, 20)` -> `self::MAX_LOOPS` (1 occurrence)
* the 3 step branch is now guarded by an injected feature flag that defaults to `true`

The loop arithmetic, ordering (`usort` by profit desc) and output keys are byte identical to the original.

## 4. Stages 2-5 - remaining plan

Stage 1 intentionally stops before touching HTTP entry points, because the constitution mandated gates (`php artisan test`, `pint`, `phpstan`, `npm run build`) **cannot be executed in this environment** (no PHP binary, no `vendor/`, no `node_modules/`). Rewiring controllers and routes without a green test run would be an unverifiable change to every API endpoint.

### Stage 2 - Form Requests (removes inline validation)

* `app/Http/Requests/Task/ScheduledTaskRequest.php` (abstract base with the shared rules and the `interval >= 1 minute` rule) + `StoreScheduledTaskRequest`, `UpdateScheduledTaskRequest` - kills the 50 line duplicated block.
* `app/Http/Requests/Account/{StoreAccountRequest,AccountActionRequest,UpdateAccountSessionRequest}.php`
* `app/Http/Requests/Market/{StoreMarketServerRequest,UpdateMarketServerRequest,UpdateMarketSettingsRequest,MarketDataRequest,MarketLogRequest}.php`
* `app/Http/Requests/Settings/UpdateSettingsRequest.php`, `app/Http/Requests/Log/LogIndexRequest.php`

### Stage 3 - remaining services

* `MarketAnalyticsService`, `MarketBulkService` - assemble the cached payloads from the Stage 1 collaborators (this is where the remaining duplication between `getAnalytics()` and `getBulk()` disappears).
* `MarketServerService` + `Results/ServerOperationResult`, `ServerPresetProvider`, `MarketSettingsService` - server CRUD, verification, preset and sync interval / TTL logic; centralises the repeated `load('account:id,username,nickname,region,status')` and the double `bumpDataVersion($serverId)` + `bumpDataVersion('global')`.
* `Tasks/ScheduledTaskService`, `Tasks/Contracts/TaskActivityLogger` + `BotLogTaskActivityLogger` (removes 6 duplicated `BotLog::create()` blocks), `Tasks/Validation/BuffPayloadValidator`, `Tasks/Validation/FriendZoneCache`.
* `Accounts/AccountActionRegistry` + `AccountActionExecutor` + `Handlers/{StopProduction,StartProduction,ApplyBuff,SendSpecialist}Handler` - replaces the `switch` on action type with OCP compliant strategies; plus `AccountDeletionService`, `FriendZoneService`, `DashboardStatsService`.

### Stage 4 - thin controllers and routes

Split `MarketAnalyticsController` into `app/Http/Controllers/Market/{MarketServerController,MarketSettingsController,MarketSyncController,MarketCatalogController,MarketAnalyticsController,MarketArbitrageController,MarketBulkController,MarketVersionController,MarketSyncLogController,PublicMarketServerController}.php`, rewrite `Account`, `ScheduledTask`, `Settings`, `Log`, `Dashboard` controllers to the validate-delegate-respond shape, and repoint `routes/api.php`. Every URI, HTTP status and response key stays byte compatible; the existing feature tests (`MarketAnalyticsTest`, `MarketCacheTest`, `ScheduledTaskTest`, ...) are the regression net.

### Stage 5 - remaining Resources

`AccountResource`, `ScheduledTaskResource`, `ScheduledTaskStatusResource`, `BotLogResource`, `MarketServerResource`, `MarketSyncLogResource`.

## 5. Verification status

| Gate | Status |
| --- | --- |
| `php artisan test` | **not run** - no PHP binary and no `vendor/` in this environment |
| `./vendor/bin/pint --test` | **not run** - same reason |
| `./vendor/bin/phpstan analyse` | **not run** - same reason |
| `npm run build` | **not run** - no `node_modules/` |
| Static review | done - new code follows `declare(strict_types=1)`, PHP 8.1 syntax only, explicit return types, constructor property promotion, no `env()` outside `config/` |
| Behaviour of extracted arbitrage code | asserted during extraction (see section 3) |

Before merging, run all four gates locally.
