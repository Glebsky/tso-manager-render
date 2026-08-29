# Design: `produce_buff`

Все сигнатуры ниже — нормативные. Отклонения фиксируются в `decisions.md`.

---

## 1. Карта изменений

### Новые файлы

```
app/Enums/ProductionRejectionReason.php
app/Services/Amf/Vo/defaultGame_Communication_VO_dTimedProductionVO.php
app/Services/Game/Production/ProductionCatalogInterface.php
app/Services/Game/Production/ConfigProductionCatalog.php
app/Services/Game/Production/ProductionRecipe.php
app/Services/Game/Production/ProducerBuilding.php
app/Services/Game/Production/BuffProductionCostCalculator.php
app/Services/Game/Production/ProductionCost.php
app/Services/Game/Production/ProductionOrderPolicy.php
app/Services/Game/Production/ProductionDecision.php
app/Services/Game/Production/ProductionCommandGatewayInterface.php
app/Services/Game/Production/AmfProductionCommandGateway.php
app/Services/Game/Production/BuffProducerListService.php
app/Services/Tasks/Handlers/ProduceBuffHandler.php
app/Http/Controllers/BuffProducerController.php
app/Http/Resources/BuffProducerResource.php
app/Console/Commands/ImportProductionCatalog.php
config/game_production.php
```

### Изменяемые файлы

```
app/Enums/TaskType.php                        + ProduceBuff
app/Services/TsoAmfService.php                + CMD_START_TIMED_PRODUCTION, queueTimedProduction()
app/Services/ZoneParserService.php            + productionType, upgradeLevel, productionQueues
app/Support/Zone/ZoneSnapshot.php             + productionQueues(), producerBuildings()
storage/app/parse_zone.py                     + извлечение новых атрибутов
app/Providers/TaskServiceProvider.php         + регистрация хендлера и биндингов
app/Http/Requests/Tasks/ScheduledTaskRequest.php + produceBuffRules()
routes/api.php                                + GET buff-producers
resources/js/views/Tasks.vue                  + тип шага produce_buff
lang/{en,ru,uk}/ui.php                        + строки
```

**Запрещено менять:** `startProduction()`, `stopProduction()`,
`StartProductionHandler`, `StopProductionHandler`, `buildServerAction()`,
`Amf3Encoder` (префикс `defaultGame_` уже работает, см. `data-sources.md` §2.4).

---

## 2. Протокольный слой

### 2.1. VO

```php
<?php

declare(strict_types=1);

namespace App\Services\Amf\Vo;

/**
 * AMF alias: defaultGame.Communication.VO.dTimedProductionVO
 * Источник полей: client_scripts.txt:59375-59420
 *
 * Клиент игры заполняет только 5 полей. Остальные — ответ сервера.
 */
class defaultGame_Communication_VO_dTimedProductionVO
{
    public ?int $productionType = null;

    public ?string $type_string = null;

    public ?int $amount = null;

    public ?int $stacks = null;

    public ?int $buildingGrid = null;
}
```

ВНИМАНИЕ: имя свойства — ровно `type_string` с подчёркиванием, как в клиенте.
Не `typeString`, не `type`. Камелкейс-переименование сломает десериализацию на
сервере игры.

Остальные 9 полей класса игры (`index`, `uniqueId`, `playerId`, `producedItems`,
`collectedTime`, `modifiedProductionMultiplier`, `modifiedProductionAdder`,
`modifiedInstantFinishCostMultiplier`, `modifiedInstantFinishCostAdder`)
в PHP-класс **не добавляются**: клиент игры их не отправляет, а лишние поля
со значением `null` изменили бы тело AMF-запроса (AC-2, AC-3).

### 2.2. Метод сервиса

В `TsoAmfService`:

```php
public const CMD_START_TIMED_PRODUCTION = 91;

/**
 * Ставит заказ в очередь производства здания.
 *
 * ВНИМАНИЕ: в отличие от всех остальных команд проекта, payload здесь —
 * сам dTimedProductionVO, БЕЗ обёртки dServerAction.
 * Источник: client_scripts.txt:307959-307965.
 */
public function queueTimedProduction(
    Account $account,
    int $grid,
    int $productionType,
    string $typeString,
    int $amount = 1,
    int $stacks = 1,
): string {
    $vo = new defaultGame_Communication_VO_dTimedProductionVO;
    $vo->productionType = $productionType;
    $vo->type_string = $typeString;
    $vo->amount = $amount;
    $vo->stacks = $stacks;
    $vo->buildingGrid = $grid;

    return $this->sendServerCall($account, self::CMD_START_TIMED_PRODUCTION, $vo);
}
```

Никакого `buildServerAction()`. Если `sendServerCall()` внутри жёстко требует
`dServerAction` — расширить его тип параметра до `mixed`/union, не меняя поведения
для существующих вызовов (INV-2).

### 2.3. Шлюз

```php
interface ProductionCommandGatewayInterface
{
    /**
     * @return string сырой AMF-ответ игры
     */
    public function queueOrder(
        Account $account,
        int $grid,
        int $productionType,
        string $recipeName,
        int $amount,
        int $stacks,
    ): string;
}
```

`AmfProductionCommandGateway` — единственная реализация, делегирует в
`TsoAmfService::queueTimedProduction()`.

---

## 3. Каталог

### 3.1. Формат конфига

`config/game_production.php` (отдельный файл, а не раздувание `config/game.php`,
потому что объём генерируемый и большой):

```php
<?php

// СГЕНЕРИРОВАНО: php artisan tso:import-production-catalog
// Источники: docs/references/icons.xml, docs/references/globals.xml
// НЕ РЕДАКТИРОВАТЬ ВРУЧНУЮ.

return [
    'generated_at' => '2026-08-28T00:00:00+00:00',

    // building_name => productionType
    'producers' => [
        'ProvisionHouse' => 1,
        'Bookbinder' => 2,
        'ProvisionHouse2' => 5,
        'Laboratory' => 16,
        // ... всего 68
    ],

    // productionType => список рецептов
    'recipes' => [
        1 => [
            [
                'name' => 'ProductivityBuffLvl3',
                'group' => 0,
                'duration_seconds' => 1800,
                'buff_type' => 'Timed',
                'requires_upgrade_level_min' => 0,
                'requires_upgrade_level_max' => 99,
                'requires_event' => null,
                'requires_quest' => null,
                'costs' => [
                    ['resource' => 'Fish', 'count' => 120],
                    ['resource' => 'Bread', 'count' => 60],
                    ['resource' => 'Sausage', 'count' => 20],
                ],
                'costs_known' => true,
            ],
            // ...
        ],
    ],
];
```

### 3.2. Команда генерации

```
php artisan tso:import-production-catalog
    [--icons=docs/references/icons.xml]
    [--globals=docs/references/globals.xml]
    [--output=config/game_production.php]
    [--dry-run]
```

Алгоритм:

1. Потоково (`XMLReader`, не `simplexml_load_file` — 4.3 МБ) пройти `icons.xml`,
   собрать `<Building name productionType>` где `productionType` есть и `>= 0`.
2. Пройти `globals.xml`, собрать:
   - `<TimedProductionList id>/<TimedProduction>` — явные списки (механизм B);
   - `<Buff produceable="true">` — динамический пул бафов (механизм A).
3. Для hardcoded-типов (см. OQ-1 в `decisions.md`) — сопоставить пул бафов по `group`.
4. Записать PHP-файл с детерминированной сортировкой ключей и записей
   (иначе diff будет шумом при каждом запуске).

Команда обязана быть идемпотентной: два запуска подряд — пустой git diff.

### 3.3. Интерфейс чтения

```php
interface ProductionCatalogInterface
{
    /** @return int|null productionType или null, если здание не производитель */
    public function productionTypeFor(string $buildingName): ?int;

    /** @return ProductionRecipe[] */
    public function recipesFor(int $productionType): array;

    public function findRecipe(int $productionType, string $recipeName): ?ProductionRecipe;
}
```

`ProductionRecipe` и `ProducerBuilding` — `final readonly class` с типизованными
свойствами, без логики доступа к конфигу.

---

## 4. Снапшот зоны

### 4.1. Новые ключи (аддитивно, ADR-13)

В каждом элементе `buildings[]`:

```
production_type   int|null
upgrade_level     int|null
```

На верхнем уровне снапшота:

```
production_queues  array<int, array{ orders: list<array{
    type_string: string,
    amount: int,
    collected_time: float|null,
    ready_for_deliver: bool|null
}> }> | null
```

Ключ внешнего массива — `productionType` (INV-5).
Значение `null` у `production_queues` означает «разобрать не удалось» и
ведёт к `QueueDataUnavailable` (INV-4). Пустой массив означает «очередей нет» —
это валидное состояние. Различать эти два случая обязательно.

### 4.2. Методы `ZoneSnapshot`

```php
/** @return array<int, ProductionQueueState>|null */
public function productionQueues(): ?array;

/** @return list<array{grid:int, building_name:string, production_type:int, upgrade_level:int|null}> */
public function producerBuildings(): array;

public function productionQueueFor(int $productionType): ?ProductionQueueState;
```

### 4.3. Источник данных в AMF-ответе

Согласно `client_scripts.txt:60136–60142`, в player VO есть
`timedProductions_vector[TIMED_PRODUCTION_TYPE.BUFF]`, сериализуемый как
`BuffProduction_vector` (рядом — `MilitaryUnitRecruitments_vector`,
`EliteUnitRecruitments_vector`).

**Задача этапа 2:** найти эти ключи в реальном AMF-ответе зоны и зафиксировать
точные имена в `data-sources.md`. Пока имена не подтверждены фикстурой —
не писать парсер по догадке.

---

## 5. Политика

```php
final readonly class ProductionDecision
{
    private function __construct(
        public bool $allowed,
        public ?ProductionRejectionReason $reason,
        public ?ProductionRecipe $recipe,
        public ?int $productionType,
    ) {}

    public static function allow(ProductionRecipe $recipe, int $productionType): self;

    public static function reject(ProductionRejectionReason $reason): self;
}
```

```php
final readonly class ProductionOrderPolicy
{
    public function __construct(private ProductionCatalogInterface $catalog) {}

    public function decide(
        ZoneSnapshot $snapshot,
        int $grid,
        int $expectedProductionType,
        string $recipeName,
        int $amount,
    ): ProductionDecision;
}
```

Порядок проверок (важен, от дешёвых к дорогим и от более конкретных причин):

1. Здание с таким `grid` есть в снапшоте? → `BuildingNotFound`
2. У него `production_type !== null && >= 0`? → `NotAProducer`
3. `production_type === $expectedProductionType`? → `ProductionTypeMismatch`
4. Здание не в апгрейде? → `BuildingUpgrading`
5. Рецепт есть в каталоге? → `RecipeUnknown`
6. `upgrade_level` в диапазоне рецепта? → `RecipeLevelLocked`
7. `production_queues !== null`? → `QueueDataUnavailable`
8. Иначе → `allow()`

**`QueueFull` здесь НЕ выставляется** — см. OQ-2: лимит очереди неизвестен,
поэтому решает сервер игры, а мы переводим его код ошибки в эту причину пост-фактум
через `GameErrorResolver`.

Политика — чистый объект: без сети, без БД, без фасадов, без времени.
Прецедент — `MinePlacementPolicy` (`build-mine` ADR-2, ADR-3).

---

## 6. Калькулятор стоимости

```php
final readonly class ProductionCost
{
    /** @param array<string,int> $resources resource_name => количество */
    public function __construct(
        public array $resources,
        public int $durationSeconds,
        public bool $complete,
    ) {}

    public function plus(self $other): self;
}

final readonly class BuffProductionCostCalculator
{
    public function forOrder(ProductionRecipe $recipe, int $amount, int $stacks): ProductionCost;

    /** @param list<array{recipe:ProductionRecipe, amount:int, stacks:int}> $orders */
    public function forSequence(array $orders): ProductionCost;
}
```

Формулы (FR-8):

```
resources[r] = recipe.costs[r].count * amount * stacks
durationSeconds = recipe.duration_seconds * amount * stacks
complete = recipe.costs_known
```

`forSequence()` складывает ресурсы покомпонентно; `complete` — логическое И
всех слагаемых (FR-8.5).

**Целочисленная арифметика только.** Никаких `float` в стоимостях товаров.

---

## 7. Хендлер

```php
final readonly class ProduceBuffHandler implements TaskActionHandlerInterface
{
    public function __construct(
        private ZoneSnapshotProviderInterface $zones,
        private ProductionOrderPolicy $policy,
        private ProductionCommandGatewayInterface $gateway,
        private GameResponseValidator $validator,
    ) {}

    public function supports(string $actionType): bool
    {
        return $actionType === TaskType::ProduceBuff->value;
    }

    public function handle(Account $account, array $payload): string;
}
```

Шаги `handle()`:

1. Извлечь и привести типы: `grid`, `production_type`, `recipe_name`, `amount`, `stacks`.
2. `$snapshot = $this->zones->fresh($account)` — свежий снапшот (ADR-10).
3. `$decision = $this->policy->decide(...)`.
4. Если `!$decision->allowed` — вернуть локализованную строку с префиксом из
   `TaskResultPrefix`. **Никаких исключений** (ADR-7).
5. `$raw = $this->gateway->queueOrder(...)`.
6. `$this->validator->assertOk($raw)` — технические сбои бросают исключение и
   попадают в `ActionRetryPolicy`.
7. Вернуть строку результата с именем рецепта и количеством.

Регистрация — в `TaskServiceProvider`, в тот же `TaskHandlerRegistry`, девятым
хендлером.

---

## 8. Payload шага

Структура элемента `payload.actions[]` (INV-1 — форма не меняется):

```json
{
  "task_type": "produce_buff",
  "payload": {
    "grid": 1234,
    "production_type": 1,
    "recipe_name": "ProductivityBuffLvl3",
    "amount": 3,
    "stacks": 1,
    "building_name": "Дом снабжения",
    "building_raw_name": "ProvisionHouse",
    "recipe_label": "Баф продуктивности III"
  },
  "delay_seconds": 5,
  "meta": {
    "cost": {
      "resources": { "Fish": 360, "Bread": 180, "Sausage": 60 },
      "duration_seconds": 5400,
      "complete": true
    },
    "shares_queue_with": [5678]
  }
}
```

Поля `building_name`, `building_raw_name`, `recipe_label` — только для отображения,
по образцу `apply_buff`. Исполнение их не читает.

---

## 9. API

```
GET /api/accounts/{account}/buff-producers
```

Ответ:

```json
{
  "data": [
    {
      "grid": 1234,
      "building_name": "ProvisionHouse",
      "building_label": "Дом снабжения",
      "production_type": 1,
      "upgrade_level": 4,
      "queue": {
        "used": 2,
        "orders": [
          { "recipe_name": "ProductivityBuffLvl2", "amount": 1, "ready_for_deliver": false }
        ]
      },
      "shares_queue_with": [5678],
      "recipes": [
        {
          "name": "ProductivityBuffLvl3",
          "label": "Баф продуктивности III",
          "group": 0,
          "duration_seconds": 1800,
          "availability": "available",
          "costs_known": true,
          "costs": [
            { "resource": "Fish", "resource_label": "Рыба", "count": 120 }
          ]
        }
      ]
    }
  ],
  "meta": { "queue_data_available": true }
}
```

`meta.queue_data_available: false` — снапшот не дал очередей. UI в этом случае
показывает предупреждение и не блокирует планирование (откажет политика при
исполнении по INV-4).

Логика сборки ответа — в `BuffProducerListService`, не в контроллере.
Прецедент — `ClickableBuildingListService`.

---

## 10. Frontend

Файл: `resources/js/views/Tasks.vue` (2758 строк). Новых компонентов НЕ создавать
(`collect-building` ADR-11 — переиспользовать существующие модалки).

### 10.1. Точки расширения

| Строки | Что делать |
| --- | --- |
| 117, 131 | рендер шага в списке sequence |
| 261–266 | пункт `produce_buff` в дропдауне типов |
| 280–343 | блок формы выбора (здание / рецепты / количество / сводка стоимости) |
| 759–857 | новые ref: `showProducerModal`, `selectedProducer`, `selectedRecipes`, `recipeAmounts` |
| 1181–1198 | иконка и лейбл типа шага |
| 1303–1309 | ветка в `resetPayload` / `onStepActionTypeChange` |
| ~1345 | ветка в `addStepToSequence()` |

### 10.2. Ветка `addStepToSequence()`

```js
if (stepActionType.value === 'produce_buff') {
    if (!selectedProducer.value) {
        showToast(t('tasks.toast.select_producer_first'), 'error');
        return;
    }
    if (!selectedRecipes.value.length) {
        showToast(t('tasks.toast.select_recipe_first'), 'error');
        return;
    }
    // ADR-3: один шаг на рецепт, amount внутри шага (не разворачивать в amount шагов!)
    for (const recipe of selectedRecipes.value) {
        const amount = Number(recipeAmounts.value[recipe.name] || 1);
        sequenceActions.value.push({
            task_type: 'produce_buff',
            payload: {
                grid: selectedProducer.value.grid,
                production_type: selectedProducer.value.production_type,
                recipe_name: recipe.name,
                amount,
                stacks: 1,
                building_name: selectedProducer.value.building_label,
                building_raw_name: selectedProducer.value.building_name,
                recipe_label: recipe.label,
            },
            delay_seconds: Number(stepDelay.value || 0),
            meta: {
                cost: computeRecipeCost(recipe, amount, 1),
                shares_queue_with: selectedProducer.value.shares_queue_with,
            },
        });
    }
    showToast(t('tasks.toast.action_added'), 'success');
    return;
}
```

### 10.3. Сводка стоимости

`computed`, агрегирующий `meta.cost.resources` всех шагов `produce_buff`
в `sequenceActions`. Рендер — над списком шагов. Если хотя бы один шаг имеет
`complete: false` — показать пометку «стоимость неполная».

Дублирование формулы стоимости на фронте и бэкенде допустимо (фронту она нужна
для предпросмотра без роундтрипа), но обе реализации обязаны иметь тесты с
одними и теми же ожиданиями (AC-5).

---

## 11. Локализация

Новые ключи в `lang/{en,ru,uk}/ui.php`:

```
tasks.action.produce_buff
tasks.type_label.produce_buff
tasks.toast.select_producer_first
tasks.toast.select_recipe_first
tasks.production.queue_used
tasks.production.shares_queue_warning
tasks.production.cost_summary
tasks.production.cost_incomplete
tasks.production.availability_unknown
tasks.production.queue_data_unavailable
tasks.production.rejected.building_not_found
tasks.production.rejected.not_a_producer
tasks.production.rejected.production_type_mismatch
tasks.production.rejected.queue_data_unavailable
tasks.production.rejected.queue_full
tasks.production.rejected.recipe_unknown
tasks.production.rejected.recipe_level_locked
tasks.production.rejected.building_upgrading
```

После правки — `php artisan tso:lang:export-frontend`.
