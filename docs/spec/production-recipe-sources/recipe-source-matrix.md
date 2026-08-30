# Матрица источников рецептов — ГЛАВНЫЙ ДОКУМЕНТ

Если ты прочтёшь только один файл из всего пакета — читай этот.

---

## 1. Центральная идея, которую надо понять до кода

В игре каждое здание-производитель имеет числовой `productionType`. Казалось бы, рецепты для типа `N` лежат в `<TimedProductionList id="N">`.

**Это верно только для 62 списков из 71.** Девять списков — с id 0, 1, 2, 3, 4, 6, 7, 8, 11 — **пусты намеренно**. В каждом из них лежит XML-комментарий, который объясняет, где на самом деле берутся данные.

Дословные комментарии из GLOBALS:

```
id=0   Military Units: defined by the produceable MilitaryUnits
id=1   Buffs: defined by the produceable Buffs (produceable="true")
id=2   Skillpoints: defined by the skill points in the skills.xml
id=3   Effects
id=4   Collectibles
id=6   Combat 3 Weapons: defined by the produceable Buffs (produceable="true") in group 5 (group="5")
id=7   Combat 3 Units
id=8   Elite Units
id=11  Armory 2
```

Эти девять строк — корень всей задачи.

---

## 2. Полная матрица

Столбец «Источник» — имя, которое ты запишешь в `metadata[type].recipe_source` в конфиге.

| productionType | Здание | Русское название | Источник | Статус |
| --- | --- | --- | --- | --- |
| **0** | `Barracks` | Казармы | `military_units` (фильтр `isElite != true`) | ⚠ ДЕЛАТЬ |
| **1** | `ProvisionHouse` | Мастерская улучшений | `buff_pool` (без фильтра по группе) | ✓ работает |
| **2** | `Bookbinder` | Переплётчик | `skillpoints` | ⚠ ДЕЛАТЬ |
| **3** | — нет здания — | — | `unsupported:no_producer` | ✗ НЕ ДЕЛАТЬ |
| **4** | `Mayorhouse` | Ратуша | `collections` | ⚠ ДЕЛАТЬ |
| **5** | `ProvisionHouse2` | Мастерская улучшений 2 | `explicit_list` (269 рецептов) | ✓ работает |
| **6** | `ExpeditionWeaponSmith` | Оружейная | `buff_group` с `group = 5` | ⚠ ДЕЛАТЬ |
| **7** | `Barracks3` | Казармы 3 | `unsupported:needs_tier_data` | ⏸ ОТЛОЖЕНО, OQ-7 |
| **8** | `EliteBarracks` | Элитные казармы | `military_units` (фильтр `isElite == true`) | ⚠ ДЕЛАТЬ |
| **11** | `ExpeditionWeaponSmith2` | Оружейная 2 | `buff_group` с `group = 11` | ⚠ ДЕЛАТЬ |
| **12–72** | 60 зданий | разные | `explicit_list` | ✓ работает |

Отсутствующие номера типов: **3, 9, 10, 57** — у них нет ни одного здания в ICONS. Это норма, а не баг.

### Итого надо реализовать три новых источника

1. `military_units` — закрывает типы 0 и 8;
2. `skillpoints` — закрывает тип 2;
3. `collections` — закрывает тип 4;

плюс один режим существующего:

4. `buff_group` — `buff_pool` с обязательным фильтром по `group`, закрывает типы 6 и 11.

Плюс один служебный:

5. `unsupported:<причина>` — явная отметка «мы знаем про этот тип и осознанно его не делаем».

---

## 3. Источник `skillpoints` (тип 2, Переплётчик)

### 3.1. Где брать

Файл `SKILLPOINTS` → `<scienceSystem><skillPoints><skillPoint>`.

### 3.2. Правило преобразования

Каждый `<skillPoint>` → ровно один рецепт.

| Поле рецепта | Откуда |
| --- | --- |
| `name` | `skillPoint@id` |
| `group` | `0` (вкладок нет, панель Переплётчика показывает ровно 3 кнопки) |
| `duration_seconds` | `productionLevel@productionTime` первого уровня (`amountProduced="0"`) |
| `costs` | `<cost>` первого уровня |
| `costs_known` | `true` |
| `instant_finish_cost` | `skillPoint@instantFinishCost` |
| `cost_tiers` | **НОВОЕ ПОЛЕ.** Массив всех ценовых уровней |
| `max_amount_per_order` | `1` (см. §3.4) |

### 3.3. Прогрессивная цена — читай внимательно

Стоимость очка навыка **растёт по мере того, как игрок их производит**. Это единственный источник в игре с такой механикой.

Пример для `Manuscript`:

| Уровень | Порог `amountProduced` | SimplePaper | Nib | Coin |
| --- | --- | --- | --- | --- |
| 1 | 0 | 250 | 200 | 10 |
| 2 | 10 | 275 | 220 | 11 |
| 3 | 20 | 305 | 240 | 12 |
| … | … | … | … | … |
| 10 | 90 | см. `fixtures/skillpoints.json` | | |

Правило выбора уровня: берётся **последний** уровень, у которого `amountProduced <= уже_произведено_игроком`.

**Проблема:** мы НЕ знаем, сколько очков игрок уже произвёл. Этого числа нет в снапшоте зоны в явном виде (см. OQ-2).

**Решение для v1 (делай именно так):**

1. В каталог кладём **все** ценовые уровни в поле `cost_tiers`.
2. В поле `costs` кладём стоимость первого уровня (самая дешёвая).
3. В поле `cost_is_lower_bound` ставим `true`.
4. UI показывает стоимость с приставкой «от» и подсказкой: «цена растёт с количеством уже произведённых».

**Категорически запрещено** показать стоимость первого уровня как точную. Пользователь увидит одну цифру, а спишется другая — это считается багом реализации.

### 3.4. Ограничение amount = 1

Клиент для Переплётчика использует отдельную панель `SkillProductionPanel`, где в методе `Order()` жёстко задано:

```actionscript
_local_2.productionType = this.mProductionType;
_local_2.type_string   = this.mSelectedSkillPoint.id_string;
_local_2.amount        = 1;              // <<< ЖЁСТКО 1
_local_2.buildingGrid  = this.mBuilding.GetGrid();
```

Ни `stacks`, ни слайдера количества там нет.

**Следствие:** для типа 2 выставляй `max_amount_per_order = 1` и `max_stacks = 1`, а в UI блокируй слайдеры. Сервер, возможно, примет и больше, но мы не проверяли это на живом трафике, а значит не имеем права так делать (иерархия истины, ранг 1).

### 3.5. Подтверждение живым трафиком

В реальном снапшоте зоны есть заказ Переплётчика:

```
productionType = 2
type_string    = "Tome"
amount         = 1
stacks         = 1
collectedTime  = 259200000.0
```

А в XML у `Tome`: `productionTime="259200"`. Значит 259 200 000 мс = 259 200 с. **Совпадение точное.** Это одновременно:

- подтверждает, что `skillPoint@id` — это именно `type_string`;
- подтверждает, что `productionTime` — в секундах;
- закрывает старый открытый вопрос OQ-5 про смысл `collectedTime`.

---

## 4. Источник `military_units` (типы 0 и 8)

### 4.1. Правило разделения

Клиент делит юниты ровно одним условием:

```actionscript
if ((_local_12.GetIsElite() && mProductionType == ELITE_UNITS) ||
    (!_local_12.GetIsElite() && mProductionType == MILITARY_UNIT))
```

Значит:

```
isElite == "true"  → productionType 8 (EliteBarracks)
иначе               → productionType 0 (Barracks)
```

Никаких других фильтров (по уровню игрока, по тиру, по группе) в этой ветке НЕТ. Вкладки тоже отключаются: `buttonBar.dataProvider = null`.

### 4.2. Точный состав

**Тип 0 — Казармы, 9 рецептов:**

| id | type (= `type_string`) | Секунд | Стоимость |
| --- | --- | --- | --- |
| 1 | `Recruit` | 180 | Population 1, Beer 5, BronzeSword 10 |
| 2 | `Militia` | 480 | Population 1, Beer 10, IronSword 10 |
| 3 | `Bowman` | 240 | Population 1, Beer 10, Bow 10 |
| 4 | `Cavalry` | 720 | Population 1, Beer 30, Horse 40 |
| 5 | `Soldier` | 720 | Population 1, Beer 15, SteelSword 10 |
| 6 | `Longbowman` | 480 | Population 1, Beer 20, Longbow 10 |
| 7 | `EliteSoldier` | 1200 | Population 1, Beer 50, TitaniumSword 10 |
| 8 | `Crossbowman` | 720 | Population 1, Beer 50, Crossbow 10 |
| 9 | `Cannoneer` | 1200 | Population 1, Beer 50, Cannon 10 |

⚠ Обрати внимание: юнит с именем `EliteSoldier` **НЕ является элитным** (`isElite` у него отсутствует). Он идёт в тип 0. Не фильтруй по имени — только по атрибуту.

**Тип 8 — Элитные казармы, 7 рецептов:**

| id | type | Секунд | Стоимость |
| --- | --- | --- | --- |
| 10 | `Swordsman` | 900 | Population 1, Beer 50, PlatinumSword 1 |
| 11 | `MountedSwordsman` | 1800 | Population 1, Beer 50, PlatinumSword 2, BattleHorse 2 |
| 12 | `Knight` | 1200 | Population 1, Beer 50, BattleHorse 1 |
| 13 | `Marksman` | 900 | Population 1, Beer 50, Archebuse 1 |
| 14 | `ArmoredMarksman` | 1800 | Population 1, Beer 50, Archebuse 1, PlatinumSword 1 |
| 15 | `MountedMarksman` | 1800 | Population 1, Beer 50, Archebuse 2, BattleHorse 2 |
| 16 | `Besieger` | 1200 | Population 1, Beer 50, Mortar 1 |

Все элитные имеют `instantBuildCosts="15"`.

### 4.3. Особенность ресурса `Population`

`Population` — это не обычный товар из склада, а свободное население. Калькулятор стоимости обязан считать его отдельно и помечать флагом `is_population = true`, чтобы UI не пытался искать его в остатках склада и не рисовал красным «не хватает».

### 4.4. Какой `type_string` отправлять

Атрибут `type`. **Не `id`** (число) и не локализованное имя.

---

## 5. Источник `collections` (тип 4, Ратуша)

### 5.1. Правило преобразования

Каждый `<collection>` внутри `<collections>` → один рецепт.

| Поле рецепта | Откуда |
| --- | --- |
| `name` | `collection@name` |
| `group` | `0` |
| `duration_seconds` | `collection@productionTime` |
| `costs` | `<resource name= amount=>` → `{resource, count}` |
| `costs_known` | `true` если есть хоть один `<resource>` |
| `instant_finish_cost` | `collection@InstantBuildCosts` (заглавная I!) |
| `requires_event` | `collection@requiresEvent` |
| `requires_player_level_min` | `collection@pLvl` или `collection@minLevel` |
| `output_buff_name` | `collection@outBuffName` |

### 5.2. Фильтрация по событиям

Большинство коллекций имеют `requiresEvent` (Рождество, Пасха и т. д.). В каталог кладём **все 16**, но в рантайме политика заказа обязана отсеять те, чьё событие не активно.

Как узнать, активно ли событие: через `requirements.timedProductionRequirements_vector` в снапшоте зоны — см. `evidence.md` §5. Это предпочтительнее любой самодельной логики по датам.

### 5.3. Нерешённый вопрос

Не доказано трафиком, что `type_string` для коллекции — это `collection@name`, а не `outBuffName`. См. **OQ-4**. До закрытия OQ-4 тип 4 импортируется в каталог, но в UI помечается флагом `unverified_protocol = true`.

---

## 6. Источник `buff_group` (типы 6 и 11)

### 6.1. Правило

Берём тот же пул `<Buff produceable="true">`, но с жёстким фильтром по атрибуту `group`:

| productionType | Здание | Фильтр | Найдено бафов |
| --- | --- | --- | --- |
| 6 | `ExpeditionWeaponSmith` | `group == 5` | 6 |
| 11 | `ExpeditionWeaponSmith2` | `group == 11` | 6 |

Значение для типа 6 взято прямо из XML-комментария. Значение для типа 11 выведено из того, что бафы второй оружейной (`…Lance2`, `…Combat3Weapon…2`) имеют `group="11"`. Совпадение номера группы с номером типа здесь случайное — **не пиши код вида `group = productionType`**. Задай соответствие явной картой в конфиге импортёра:

```php
'buff_group_map' => [
    6  => 5,
    11 => 11,
],
```

### 6.2. Почему это отдельный источник, а не `buff_pool`

У типа 1 фильтра по группе нет — там вкладки строятся по всем встретившимся группам. У типов 6 и 11 группа ровно одна. Если смешать эти случаи, в Оружейной появятся все 299 бафов игры, и сервер будет отвечать ошибкой на каждый второй заказ.

---

## 7. Тип 7 (Combat 3 Units) — почему отложен

Клиент для этого типа использует **другой класс данных**:

```actionscript
cMilitaryUnitData.GetAllUnitDataByTier(true, this.mCurrentSelectedUnitTier)
```

Здесь `cMilitaryUnitData` ≠ `cMilitaryUnitDescription`, и у него есть поле `mTier`. Но:

1. атрибута `tier` у `<MilitaryUnit>` в XML нет;
2. `mCurrentSelectedUnitTier` — это состояние UI, выбранное игроком, а не свойство рецепта;
3. у нас нет живого трафика заказа с `productionType = 7`.

**Три условия не выполнены → не делаем.** Записываем в каталог:

```php
7 => ['recipe_source' => 'unsupported:needs_tier_data', 'recipes' => []],
```

UI показывает честное сообщение: «Поддержка этого здания ещё не реализована», а не «Нет доступных рецептов». Это разные состояния, и путать их нельзя.

---

## 8. Сводная таблица ожидаемого числа рецептов

Используй эти числа как ассерты в тестах.

| Тип | Источник | Ожидаемо рецептов |
| --- | --- | --- |
| 0 | military_units | **9** |
| 1 | buff_pool | **299** |
| 2 | skillpoints | **3** |
| 4 | collections | **16** |
| 5 | explicit_list | **269** |
| 6 | buff_group(5) | **6** |
| 7 | unsupported | **0** (ожидаемо) |
| 8 | military_units | **7** |
| 11 | buff_group(11) | **6** |

Общее число типов с непустым каталогом после работы: **68 из 69** производителей (единственное исключение — тип 7).
