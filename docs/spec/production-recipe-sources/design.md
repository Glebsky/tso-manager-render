# Design

Этот документ описывает, **как** реализовать требования из `requirements.md`. Структура классов и имена — обязательные, не переизобретай.

---

## 1. Главное архитектурное решение (ADR-10)

**Решение:** вводим интерфейс источника рецептов с шестью реализациями. Правило выбора реализации — в одной декларативной карте.

**Альтернатива, которую мы ОТВЕРГЛИ:** добавить ещё несколько `if`-веток в существующий `ImportProductionCatalog.php`.

**Почему отвергли:** сейчас в команде 375 строк и две ветки. С шестью источниками станет 700+ строк и шесть веток. Это нарушает SRP из `constitution.md` и делает невозможным юнит-тестирование каждого источника отдельно.

**Последствия:** добавление седьмого источника (например, тип 7 после закрытия OQ-7) будет стоить один новый класс и одну строку в карте.

---

## 2. Интерфейс источника

Путь: `app/Services/Game/Production/Sources/RecipeSourceInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Game\Production\Sources;

use App\Services\Game\Production\ProductionRecipe;

interface RecipeSourceInterface
{
    /**
     * Стабильный идентификатор, попадает в metadata[type].recipe_source.
     * Примеры: 'buff_pool', 'military_units', 'skillpoints'.
     */
    public function id(): string;

    /**
     * Возвращает рецепты для указанного productionType.
     *
     * @param  array<string, mixed>  $options параметры из карты, например ['group' => 5]
     * @return list<ProductionRecipe>
     */
    public function recipesFor(int $productionType, array $options = []): array;
}
```

Важно: источник **не читает файлы сам**. Файлы читаются один раз загрузчиком и передаются в конструктор как уже разобранные структуры. Иначе мы будем парсить 4.5 МБ XML шесть раз и нарушим NFR-7.

---

## 3. Шесть реализаций

Все в `app/Services/Game/Production/Sources/`.

| Класс | `id()` | Источник данных | Опции |
| --- | --- | --- | --- |
| `ExplicitListRecipeSource` | `explicit_list` | `<TimedProductionList id="N">` | — |
| `BuffPoolRecipeSource` | `buff_pool` | все `<Buff produceable="true">` | — |
| `BuffGroupRecipeSource` | `buff_group` | то же + фильтр | `['group' => int]` **обязательна** |
| `MilitaryUnitRecipeSource` | `military_units` | `<MilitaryUnit produceable="true">` | `['elite' => bool]` **обязательна** |
| `SkillPointRecipeSource` | `skillpoints` | `<skillPoint>` | — |
| `CollectionRecipeSource` | `collections` | `<collection>` | — |

Плюс служебный `UnsupportedRecipeSource` с `id()` вида `unsupported:needs_tier_data`, возвращающий пустой массив.

### Правило об обязательных опциях

Если `BuffGroupRecipeSource` вызван без `group` — **бросай `InvalidArgumentException`**. Не подставляй дефолт. Дефолт здесь — это тихая ошибка, которая приведёт к 299 рецептам в Оружейной. Аналогично с `elite`.

---

## 4. Карта соответствия

Путь: `config/game_production_sources.php` (новый файл, не генерируется, пишется руками).

```php
<?php

declare(strict_types=1);

return [
    // productionType => ['source' => id, 'options' => [...]]
    0  => ['source' => 'military_units', 'options' => ['elite' => false]],
    2  => ['source' => 'skillpoints',    'options' => []],
    4  => ['source' => 'collections',    'options' => []],
    6  => ['source' => 'buff_group',     'options' => ['group' => 5]],
    7  => ['source' => 'unsupported',    'options' => ['reason' => 'needs_tier_data']],
    8  => ['source' => 'military_units', 'options' => ['elite' => true]],
    11 => ['source' => 'buff_group',     'options' => ['group' => 11]],

    // Дефолт для всех остальных типов задаётся отдельно:
    'default' => ['source' => 'explicit_list', 'options' => []],

    // Особый случай: тип 1 берёт весь пул бафов.
    1  => ['source' => 'buff_pool', 'options' => []],
];
```

### Алгоритм разрешения

```
для каждого productionType из ICONS (69 штук):
    если в карте есть явная запись → берём её
    иначе → берём 'default' (explicit_list)

    рецепты = источник.recipesFor(productionType, options)

    если рецептов 0 И источник не 'unsupported':
        → ошибка импорта, нарушен INV-6
```

Последняя проверка — это главный предохранитель от повторения исходного бага.

---

## 5. Схема рецепта в конфиге

Существующие поля сохраняются. Добавляем новые, все с безопасными дефолтами, чтобы старый код не сломался.

```php
[
    // СУЩЕСТВУЮЩИЕ ПОЛЯ — не менять
    'name'                       => 'Tome',
    'group'                      => 0,
    'duration_seconds'           => 259200,
    'buff_type'                  => null,
    'requires_upgrade_level_min' => null,
    'requires_upgrade_level_max' => null,
    'requires_event'             => null,
    'requires_quest'             => null,
    'costs'                      => [
        ['resource' => 'SimplePaper', 'count' => 250, 'is_population' => false],
        ['resource' => 'Nib',         'count' => 200, 'is_population' => false],
        ['resource' => 'Coin',        'count' => 10,  'is_population' => false],
    ],
    'costs_known'                => true,

    // НОВЫЕ ПОЛЯ
    'instant_finish_cost'        => 200,      // int|null
    'max_amount_per_order'       => 1,        // int, дефолт 25
    'max_stacks_per_order'       => 1,        // int, дефолт 200
    'cost_is_lower_bound'        => true,     // bool, дефолт false
    'cost_tiers'                 => [         // list|null, только для skillpoints
        ['threshold' => 0,  'costs' => [/* ... */]],
        ['threshold' => 20, 'costs' => [/* ... */]],
    ],
    'requires_player_level_min'  => null,     // int|null, для collections
    'output_buff_name'           => null,     // string|null, для collections
    'unverified_protocol'        => false,    // bool, true для collections до OQ-4
]
```

**Критично:** поле `is_population` добавляется в КАЖДЫЙ элемент `costs`, включая старые рецепты (там всегда `false`). Это нужно, чтобы потребитель не проверял `array_key_exists`.

### Обновление DTO

`ProductionRecipe` — добавить новые свойства с дефолтами в конструкторе, чтобы существующие вызовы не сломались. Все свойства — `public readonly`.

---

## 6. Нормализатор длительности

Три разных имени атрибута — одна точка нормализации.

```php
final class DurationNormalizer
{
    private const ATTRIBUTES = ['duration', 'productionTime', 'productionTimeSeconds'];

    public function fromElement(\SimpleXMLElement $el): int
    {
        foreach (self::ATTRIBUTES as $attr) {
            if (isset($el[$attr])) {
                return (int) $el[$attr];
            }
        }

        throw new \RuntimeException(
            'No duration attribute on <' . $el->getName() . '>'
        );
    }
}
```

Порядок перебора важен: `duration` первым, так как в `<TimedProduction>` могут присутствовать оба атрибута, и тогда `duration` авторитетнее (так уже сделано в текущем коде, строка 263: `duration ?: productionTime`).

Аналогично для стоимости мгновенного завершения: `InstantFinishCostNormalizer` с списком `['instantFinishCost', 'instantBuildCosts', 'InstantBuildCosts']`.

---

## 7. Нормализатор стоимости

Три разные формы записи стоимости.

```php
final class CostNormalizer
{
    /** @return list<array{resource: string, count: int, is_population: bool}> */
    public function fromCostsBlock(\SimpleXMLElement $parent): array
    {
        // <Costs><Cost name= count=/></Costs>
    }

    /** @return list<array{resource: string, count: int, is_population: bool}> */
    public function fromLowercaseCosts(\SimpleXMLElement $parent): array
    {
        // <cost name= count=/>  — skillpoints
    }

    /** @return list<array{resource: string, count: int, is_population: bool}> */
    public function fromResources(\SimpleXMLElement $parent): array
    {
        // <resource name= amount=/>  — collections
    }
}
```

Флаг `is_population` выставляется во всех трёх методах по условию `$name === 'Population'`.

**Не делай один метод с автоопределением формы.** Каждый источник точно знает свою форму и вызывает нужный метод явно. Автоопределение скроет баги в данных.

---

## 8. Политика заказа — что добавляется

Существующая `ProductionOrderPolicy` с 8 шагами расширяется до 11. Порядок шагов важен: самые дешёвые проверки первыми.

| № | Шаг | Причина отказа | Статус |
| --- | --- | --- | --- |
| 1 | Здание есть в снапшоте | `building_not_found` | есть |
| 2 | У здания есть productionType | `not_a_producer` | есть |
| **3** | **Тип не помечен `unsupported:*`** | `production_type_unsupported` | **НОВЫЙ** |
| 4 | Тип здания совпадает с типом в пейлоаде | `production_type_mismatch` | есть |
| 5 | Рецепт есть в каталоге | `recipe_unknown` | есть |
| **6** | **`amount ≤ max_amount_per_order`** | `amount_exceeds_recipe_limit` | **НОВЫЙ** |
| 7 | `stacks ≤ max_stacks_per_order` | `stacks_exceeds_recipe_limit` | **НОВЫЙ** |
| 8 | Уровень здания достаточен | `recipe_level_locked` | есть |
| **9** | **Событие рецепта активно** | `recipe_requires_inactive_event` | **НОВЫЙ** |
| 10 | Здание не апгрейдится | `building_upgrading` | есть |
| 11 | Очередь не переполнена | `queue_full` / `queue_data_unavailable` | есть |

Шаг 3 стоит рано намеренно: если тип вообще не поддержан, бессмысленно проверять рецепт и очередь.

Все новые причины добавляются в enum `ProductionRejectionReason` и в языковые файлы всех трёх локалей (ключи `tasks.production.rejected.*`).

---

## 9. Калькулятор стоимости — изменения

`BuffProductionCostCalculator` обновляется:

1. возвращать `is_lower_bound: bool` в результате (пробрасывается из `recipe.cost_is_lower_bound`);
2. отделять `Population` в отдельный ключ `population`, не смешивая с `resources`;
3. считать суммарное время как `duration_seconds × amount × stacks`.

Форма результата (попадает в `meta.cost`):

```php
[
    'resources'        => ['SimplePaper' => 250, 'Nib' => 200, 'Coin' => 10],
    'population'       => 0,
    'duration_seconds' => 259200,
    'complete'         => true,
    'is_lower_bound'   => true,
]
```

`complete` — это старое поле, означает `costs_known`. Не путай его с `is_lower_bound`: первое — «мы вообще знаем состав», второе — «состав знаем, но числа могут быть больше».

---

## 10. UI — три состояния вместо одного

Сейчас `ProducerPicker.vue` имеет одно пустое состояние. Нужно три:

| Условие | Ключ перевода | Текст (ru) |
| --- | --- | --- |
| `recipe_source` начинается с `unsupported:` | `tasks.production.unsupported_building` | Поддержка этого здания ещё не реализована |
| Каталог пуст при поддерживаемом типе | `tasks.production.catalog_missing` | Каталог не загружен. Запустите импорт каталога |
| Каталог непуст, но всё отфильтровано | `tasks.production.no_available_recipes` | Для этой мастерской нет доступных рецептов |

Второе состояние — диагностическое. Именно оно должно было показаться в баге с Переплётчиком вместо третьего.

### Слайдеры

При `max_amount_per_order === 1` слайдер количества скрывается полностью (не дизейблится) и рядом пишется пояснение: «Это здание производит по одному предмету за заказ».

### Отображение стоимости

При `is_lower_bound === true` перед суммой ставится «≥» или «от», плюс иконка с тултипом. Просто показать число — нарушение FR-15.

---

## 11. Что НЕ трогать

Эти компоненты уже работают и покрыты тестами. Изменять их в рамках этой задачи запрещено:

- `Amf3Encoder.php` и алиасы классов;
- `TsoAmfService::queueTimedProduction()` — сигнатура уже подходит для всех типов;
- `defaultGame_Communication_VO_dTimedProductionVO` с 14 полями;
- `ProduceBuffHandler` — только если понадобится новая обработка ошибок;
- `TaskType::ProduceBuff` — новые типы таски НЕ нужны (см. `evidence.md` §2);
- `ZoneSnapshot` и `parse_zone.py` — кроме случая, когда потребуется чтение `timedProductionRequirements_vector`.

Если тебе кажется, что надо менять что-то из этого списка — остановись и задай вопрос.
