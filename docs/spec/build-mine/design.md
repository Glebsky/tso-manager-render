# Design: build_mine / upgrade_mine

Все классы: `declare(strict_types=1)`, `final`, где возможно `final readonly`,
внедрение зависимостей только через конструктор, никаких фасадов внутри домена.

## 1. Карта слоёв

```
  Транспорт / Laravel                Домен (чистый)
  ------------------                 --------------
  TsoAmfService                      MineDefinition
      ^                              DepositSnapshot
      | реализует                    BuildingSnapshot
  AmfMineCommandGateway              BuildQueueSnapshot / BuildQueueBudget
      ^                              ZoneSnapshot
      | MineCommandGatewayInterface   MinePlacementPolicy  -> PlacementDecision
      |                              MineUpgradePolicy    -> UpgradeDecision
  BuildMineHandler ------------------>|
  UpgradeMineHandler ---------------->|
      ^                               ^
      | TaskActionHandlerInterface     | MineCatalogInterface
  TaskHandlerRegistry               ConfigMineCatalog -> config/game.php
      ^
  SingleActionExecutor / SequenceStepExecutor (без изменений)

  ZoneSnapshotProviderInterface
      ^
  AmfZoneSnapshotProvider  -> TsoAmfService + ZoneParserService

  BuildableDepositController --\
  UpgradableMineController ----+--> MineTargetListService (кэш 30 c) --> те же политики
```

Правило направления зависимостей: домен не знает ни о Laravel, ни о сети, ни о
`Account`. Всё, что домен получает, — это снапшот и каталог. Это делает политики
детерминированными и полностью покрываемыми unit-тестами.

## 2. Пространства имён и файлы

| Файл | Тип |
| --- | --- |
| `app/Enums/PlacementRejectionReason.php` | enum |
| `app/Enums/UpgradeRejectionReason.php` | enum |
| `app/Services/Game/Mines/Contracts/MineCatalogInterface.php` | интерфейс |
| `app/Services/Game/Mines/Contracts/ZoneSnapshotProviderInterface.php` | интерфейс |
| `app/Services/Game/Mines/Contracts/MineCommandGatewayInterface.php` | интерфейс |
| `app/Services/Game/Mines/MineDefinition.php` | VO |
| `app/Services/Game/Mines/ConfigMineCatalog.php` | сервис |
| `app/Services/Game/Mines/DepositSnapshot.php` | VO |
| `app/Services/Game/Mines/BuildingSnapshot.php` | VO |
| `app/Services/Game/Mines/BuildQueueSnapshot.php` | VO |
| `app/Services/Game/Mines/BuildQueueBudget.php` | VO |
| `app/Services/Game/Mines/ZoneSnapshot.php` | VO-агрегат |
| `app/Services/Game/Mines/AmfZoneSnapshotProvider.php` | адаптер |
| `app/Services/Game/Mines/PlacementDecision.php` | VO |
| `app/Services/Game/Mines/UpgradeDecision.php` | VO |
| `app/Services/Game/Mines/MinePlacementPolicy.php` | политика |
| `app/Services/Game/Mines/MineUpgradePolicy.php` | политика |
| `app/Services/Game/Mines/AmfMineCommandGateway.php` | адаптер |
| `app/Services/Game/Mines/MineTargetListService.php` | сервис чтения для UI |
| `app/Services/Tasks/Handlers/BuildMineHandler.php` | обработчик задачи |
| `app/Services/Tasks/Handlers/UpgradeMineHandler.php` | обработчик задачи |

## 3. Каталог шахт

```php
final readonly class MineDefinition
{
    public function __construct(
        public string $depositName,      // IronOre
        public string $buildingName,     // IronMine
        public int $buildingNumber,      // 50
        public int $maxUpgradeLevel,     // 7
    ) {}
}

interface MineCatalogInterface
{
    /** Определение по имени руды залежи, либо null. */
    public function findByDeposit(string $depositName): ?MineDefinition;

    /** Определение по имени здания шахты, либо null. */
    public function findByBuilding(string $buildingName): ?MineDefinition;

    /** @return list<MineDefinition> */
    public function all(): array;
}

final readonly class ConfigMineCatalog implements MineCatalogInterface
{
    /** @param array<string, array{mine: string, number: int, max_level: int}> $config */
    public function __construct(private array $config) {}
}
```

Сравнение имён строгое и регистрозависимое: в `icons.xml` есть устаревшие записи
`Ironmine`/`Goldmine` с другими id, и нечувствительное сравнение может выбрать их.

## 4. Снапшот зоны

```php
final readonly class DepositSnapshot
{
    public function __construct(
        public int $grid,
        public string $name,          // IronOre
        public int $amount,
        public int $maxAmount,
        public ?int $accessible,      // null, если игра не прислала поле
    ) {}

    public function isDepleted(): bool { return $this->amount <= 0; }
}

final readonly class BuildingSnapshot
{
    public function __construct(
        public int $grid,
        public string $name,               // IronMine
        public int $upgradeLevel,
        public bool $isProductionActive,
        public bool $upgradeInProgress,
    ) {}
}

final readonly class BuildQueueSnapshot
{
    public function __construct(public int $used, public int $total) {}
}

final readonly class BuildQueueBudget
{
    public function __construct(private ?BuildQueueSnapshot $queue) {}

    // Нет данных -> ноль свободных слотов (ADR-7).
    public function freeSlots(): int
    {
        if ($this->queue === null) {
            return 0;
        }

        return max(0, $this->queue->total - $this->queue->used);
    }

    public function hasFreeSlot(): bool { return $this->freeSlots() > 0; }
}

final readonly class ZoneSnapshot
{
    /**
     * @param array<int, DepositSnapshot>  $depositsByGrid
     * @param array<int, BuildingSnapshot> $buildingsByGrid
     */
    public function __construct(
        private array $depositsByGrid,
        private array $buildingsByGrid,
        private ?BuildQueueSnapshot $buildQueue,
    ) {}

    public function depositAt(int $grid): ?DepositSnapshot;
    public function buildingAt(int $grid): ?BuildingSnapshot;
    public function hasBuildingAt(int $grid): bool;
    public function buildQueueBudget(): BuildQueueBudget;

    /** @return list<DepositSnapshot> */
    public function deposits(): array;

    /** @return list<BuildingSnapshot> */
    public function buildings(): array;
}

interface ZoneSnapshotProviderInterface
{
    /** @throws GameServerErrorException если зона вернула errorCode != 0 */
    public function forAccount(Account $account): ZoneSnapshot;
}
```

`AmfZoneSnapshotProvider` — единственное место, где сырой массив зоны
превращается в типы. Никакой другой новый класс не имеет права читать сырые ключи
вида `buildingGrid`.

## 5. Решения

```php
enum PlacementRejectionReason: string
{
    case Ok = 'ok';
    case NoDepositAtGrid = 'no_deposit_at_grid';
    case UnknownDepositType = 'unknown_deposit_type';
    case DepositEmpty = 'deposit_empty';
    case GridOccupied = 'grid_occupied';
    case DepositNotAccessible = 'deposit_not_accessible';
    case BuildQueueFull = 'build_queue_full';
}

enum UpgradeRejectionReason: string
{
    case Ok = 'ok';
    case NoBuildingAtGrid = 'no_building_at_grid';
    case NotAMine = 'not_a_mine';
    case MaxLevelReached = 'max_level_reached';
    case UpgradeAlreadyInProgress = 'upgrade_already_in_progress';
    case ProductionInactive = 'production_inactive';
    case BuildQueueFull = 'build_queue_full';
}

final readonly class PlacementDecision
{
    private function __construct(
        public bool $allowed,
        public PlacementRejectionReason $reason,
        public ?MineDefinition $definition,
        public int $grid,
    ) {}

    public static function allow(MineDefinition $definition, int $grid): self;
    public static function reject(PlacementRejectionReason $reason, int $grid, ?MineDefinition $definition = null): self;
}

final readonly class UpgradeDecision
{
    private function __construct(
        public bool $allowed,
        public UpgradeRejectionReason $reason,
        public ?MineDefinition $definition,
        public int $grid,
        public int $currentLevel,
        public int $targetLevel,
    ) {}

    public static function allow(MineDefinition $d, int $grid, int $current, int $target): self;
    public static function reject(UpgradeRejectionReason $reason, int $grid, int $current = 0, ?MineDefinition $d = null): self;
}
```

Конструкторы приватные: решение можно создать только через именованный
конструктор, поэтому невозможно собрать `allowed = true` без определения шахты.

## 6. Политики

```php
final readonly class MinePlacementPolicy
{
    public function __construct(private MineCatalogInterface $catalog) {}

    public function decide(ZoneSnapshot $zone, int $grid): PlacementDecision
    {
        $deposit = $zone->depositAt($grid);
        if ($deposit === null) {
            return PlacementDecision::reject(PlacementRejectionReason::NoDepositAtGrid, $grid);
        }

        $definition = $this->catalog->findByDeposit($deposit->name);
        if ($definition === null) {
            return PlacementDecision::reject(PlacementRejectionReason::UnknownDepositType, $grid);
        }

        if ($deposit->isDepleted()) {
            return PlacementDecision::reject(PlacementRejectionReason::DepositEmpty, $grid, $definition);
        }

        if ($zone->hasBuildingAt($grid)) {
            return PlacementDecision::reject(PlacementRejectionReason::GridOccupied, $grid, $definition);
        }

        if (! $zone->buildQueueBudget()->hasFreeSlot()) {
            return PlacementDecision::reject(PlacementRejectionReason::BuildQueueFull, $grid, $definition);
        }

        return PlacementDecision::allow($definition, $grid);
    }
}

final readonly class MineUpgradePolicy
{
    public const int DEFAULT_MAX_LEVEL = 7;

    public function __construct(private MineCatalogInterface $catalog) {}

    public function decide(ZoneSnapshot $zone, int $grid, ?int $requestedMaxLevel = null): UpgradeDecision
    {
        $building = $zone->buildingAt($grid);
        if ($building === null) {
            return UpgradeDecision::reject(UpgradeRejectionReason::NoBuildingAtGrid, $grid);
        }

        $definition = $this->catalog->findByBuilding($building->name);
        if ($definition === null) {
            return UpgradeDecision::reject(UpgradeRejectionReason::NotAMine, $grid, $building->upgradeLevel);
        }

        $target = min($requestedMaxLevel ?? $definition->maxUpgradeLevel, $definition->maxUpgradeLevel);

        if ($building->upgradeLevel >= $target) {
            return UpgradeDecision::reject(UpgradeRejectionReason::MaxLevelReached, $grid, $building->upgradeLevel, $definition);
        }

        if ($building->upgradeInProgress) {
            return UpgradeDecision::reject(UpgradeRejectionReason::UpgradeAlreadyInProgress, $grid, $building->upgradeLevel, $definition);
        }

        if (! $building->isProductionActive) {
            return UpgradeDecision::reject(UpgradeRejectionReason::ProductionInactive, $grid, $building->upgradeLevel, $definition);
        }

        if (! $zone->buildQueueBudget()->hasFreeSlot()) {
            return UpgradeDecision::reject(UpgradeRejectionReason::BuildQueueFull, $grid, $building->upgradeLevel, $definition);
        }

        return UpgradeDecision::allow($definition, $grid, $building->upgradeLevel, $building->upgradeLevel + 1);
    }
}
```

Порядок проверок значим: он определяет, какую именно причину увидит игрок.
В тестах порядок фиксируется отдельными кейсами (см. `verification.md`).

Проверка `ProductionInactive` повторяет условие рабочего юзерскрипта
(`IsBuildingInProduction()`, `user_drunken_miner.js:963`).

## 7. Шлюз команд

```php
interface MineCommandGatewayInterface
{
    /** Команда 50. Возвращает сырой ответ игры. */
    public function buildMine(Account $account, int $buildingNumber, int $grid): string;

    /** Команда 60. Возвращает сырой ответ игры. */
    public function upgradeMine(Account $account, int $grid): string;
}

final readonly class AmfMineCommandGateway implements MineCommandGatewayInterface
{
    public function __construct(private TsoAmfService $amf) {}
}
```

В `TsoAmfService` добавляются два публичных метода, построенных на существующих
хелперах `buildServerAction()` и `sendServerCall()`:

```php
public function buildBuilding(Account $account, int $buildingNumber, int $grid): string
{
    return $this->sendServerCall(
        $account,
        self::CMD_BUILD,                                                 // 50
        $this->buildServerAction($buildingNumber, $grid, 0, null),        // type = номер здания
    );
}

public function upgradeBuilding(Account $account, int $grid): string
{
    return $this->sendServerCall(
        $account,
        self::CMD_UPGRADE,                                               // 60
        $this->buildServerAction(0, $grid, 0, null),
    );
}
```

## 8. Обработчики задач

Контракт `TaskActionHandlerInterface` не меняется:

```php
public function supports(string $taskType): bool;
public function handle(Account $account, array $payload): string;
```

```php
final readonly class BuildMineHandler implements TaskActionHandlerInterface
{
    /** @var list<int> */
    private const array SESSION_ERROR_CODES = [1005, 1012];

    public function __construct(
        private ZoneSnapshotProviderInterface $zones,
        private MinePlacementPolicy $policy,
        private MineCommandGatewayInterface $gateway,
    ) {}

    public function supports(string $taskType): bool
    {
        return $taskType === TaskType::BuildMine->value;
    }

    public function handle(Account $account, array $payload): string
    {
        // 1. grid из payload, при <= 0 -> InvalidTaskTypeException
        // 2. снапшот зоны (может бросить GameServerErrorException)
        // 3. решение политики; при отказе -> лог + локализованная строка, БЕЗ вызова шлюза
        // 4. вызов шлюза, разбор кода ошибки
        // 5. 1005/1012 -> GameServerErrorException; иные коды -> warning + сообщение
        // 6. успех -> локализованная строка + сброс кэша списка целей
    }
}
```

`UpgradeMineHandler` устроен идентично, но использует `MineUpgradePolicy` и
читает `payload['max_level']`.

Оба обработчика умышленно тонкие: в них нет ни одного `if` о правилах игры —
только последовательность «снапшот, решение, отправка, разбор ответа». Все
правила живут в политиках.

## 9. Регистрация в контейнере

В `app/Providers/TaskServiceProvider.php`, метод `register()`:

```php
$this->app->singleton(MineCatalogInterface::class, fn (): ConfigMineCatalog =>
    new ConfigMineCatalog(config('game.buildings.mines', [])));

$this->app->bind(ZoneSnapshotProviderInterface::class, AmfZoneSnapshotProvider::class);
$this->app->bind(MineCommandGatewayInterface::class, AmfMineCommandGateway::class);
```

В существующий массив классов обработчиков `TaskHandlerRegistry` добавляются две
строки:

```php
BuildMineHandler::class,
UpgradeMineHandler::class,
```

Больше в реестре ничего менять нельзя: он уже работает по принципу «первый
подходящий `supports()` побеждает».

## 10. Чтение для UI

```php
final readonly class MineTargetListService
{
    private const int CACHE_TTL_SECONDS = 30;

    public function __construct(
        private ZoneSnapshotProviderInterface $zones,
        private MinePlacementPolicy $placement,
        private MineUpgradePolicy $upgrade,
        private MineCatalogInterface $catalog,
    ) {}

    /** @return list<array{grid:int,deposit_name:string,building_name:string,amount:int,max_amount:int,buildable:bool,reason:string}> */
    public function buildableDeposits(Account $account): array;

    /** @return list<array{grid:int,building_name:string,level:int,max_level:int,upgradable:bool,reason:string}> */
    public function upgradableMines(Account $account): array;

    public static function cacheKey(int $accountId, string $kind): string;
    public static function clearCache(int $accountId): void;
}
```

Сервис вызывает те же политики, что и обработчики (INV-4). Контроллеры не
содержат логики: только валидация `account_id`, вызов сервиса и ресурс ответа.
При исключении загрузки зоны возвращается `200` с пустым `data`.

## 11. Обоснование по SOLID

**SRP.** У каждого класса одна причина для изменения: `ConfigMineCatalog` меняется
при обновлении игровых номеров; `AmfZoneSnapshotProvider` — при изменении формата
ответа зоны; политики — при изменении игровых правил; `AmfMineCommandGateway` —
при изменении протокола; обработчики — при изменении контракта задач. Ни один из
этих пяти поводов не задевает остальные четыре файла.

**OCP.** Добавление новой шахты — это одна строка в `config/game.php`, без правки
кода и без новых тестов логики. Добавление нового типа действия (например
сноса) — это новый обработчик плюс одна строка в реестре; существующие классы не
трогаются.

**LSP.** `ConfigMineCatalog`, `AmfZoneSnapshotProvider` и `AmfMineCommandGateway`
полностью соблюдают контракты своих интерфейсов, включая поведение при отсутствии
данных: `findByDeposit()` возвращает `null`, а не бросает исключение, поэтому
любой тестовый двойник взаимозаменяем с боевой реализацией.

**ISP.** Три узких интерфейса вместо одного «сервиса шахт»: обработчику постройки
не нужен метод апгрейда, но он получает шлюз целиком, поэтому интерфейс
ограничен ровно двумя методами и не тянет за собой сессии, логин и рынок из
`TsoAmfService`.

**DIP.** Обработчики и сервис списков зависят только от интерфейсов. Конкретные
адаптеры подставляет провайдер. Домен (политики и VO) не зависит ни от чего,
кроме собственных типов, поэтому его тесты не требуют ни Laravel-приложения,
ни базы данных.

## 12. Соответствие правилам проекта

- `final readonly` и `declare(strict_types=1)` — как в `app/Services/Game/*`.
- Никаких строк в коде: всё через `lang/{en,ru,uk}`.
- Домен без фасадов; `Log` и `Cache` используются только в адаптерах и сервисе
  списков.
- Типизированные константы (`private const array`, `private const int`) — как в
  `CollectBuildingHandler` и `ClickableBuildingListService`.
- Новых зависимостей composer не добавляется.
