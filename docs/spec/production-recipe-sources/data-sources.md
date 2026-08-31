# Data Sources — реестр игровых XML

Цель документа: чтобы агент точно знал, какой файл брать, где он лежит и какой у него формат. Без этого не начинай кодить.

---

## 1. Где физически лежат файлы

Архив игровых XML распаковывается в проект по пути:

```
docs/references/xml/
```

Структура (212 файлов, 14 подпапок):

```
docs/references/xml/
├── README.md                       реестр всех файлов с исходными хэш-URL
├── achievements/                   4 файла   — не нужны для этой задачи
├── advent_calendar/                1 файл    — не нужен
├── collections/
│   └── collectibles_and_loot.xml   ⚠ НУЖЕН — тип 4 (Ратуша)
├── community/                      1 файл    — не нужен
├── epic_workyard/                  3 файла   — не нужны (другая механика)
├── events/                         3 файла   — справочно (requiresEvent)
├── gfx/
│   ├── gfx_settings_a742ef6a.xml   ⚠ НУЖЕН — это новая globals.xml
│   ├── gfx_settings_256c6645.xml   ⚠ НУЖЕН — это новая icons.xml
│   └── ui/combat3battlecloud.xml   справочно (тип 7)
├── help/                           1 файл    — не нужен
├── loca/
│   └── localization_oasis.xml      ○ ОПЦИОНАЛЬНО — локализация, 8.1 МБ
├── quest/                          182 файла — справочно (requiresQuest)
├── settings/
│   ├── client_configuration.xml    справочно
│   └── game_units.xml              справочно
├── shop/                           5 файлов  — не нужны
└── skill/
    ├── science_system_skillPoints.xml    ⚠ НУЖЕН — тип 2 (Переплётчик)
    └── science_system_skills_*.xml (4)   НЕ НУЖНЫ — это дерево навыков, а не производство
```

**Критично:** из 212 файлов тебе реально нужны **четыре**. Остальное не читай — потеряешь время и контекст.

---

## 2. Четыре рабочих файла

| Псевдоним в спеке | Путь | Размер | Корневой тег | Что берём |
| --- | --- | --- | --- | --- |
| `GLOBALS` | `gfx/gfx_settings_a742ef6a.xml` | 4 503 806 б | `<Root>` | `<Buff>`, `<MilitaryUnit>`, `<TimedProductionList>` |
| `ICONS` | `gfx/gfx_settings_256c6645.xml` | 1 041 660 б | `<Root>` | `<Building productionType="N">` |
| `SKILLPOINTS` | `skill/science_system_skillPoints.xml` | 273 331 б | `<scienceSystem>` | `<skillPoint>` |
| `COLLECTIONS` | `collections/collectibles_and_loot.xml` | 54 356 б | `<root>` | `<collection>` |

### Почему имена файлов такие странные

Игра раздаёт конфиги через CDN по адресам вида `GFX_HASHED/<sha1>.xml`. Имя файла — это хэш содержимого. При любом патче игры **хэш меняется**, и имя файла тоже.

**Следствие для архитектуры (важно!):** нельзя зашивать имена `gfx_settings_a742ef6a.xml` и подобные в код. Надо:

1. принимать пути опциями команды (`--globals=`, `--icons=`, `--skillpoints=`, `--collections=`);
2. если опция не задана — искать файл **по корневому тегу и маркерному узлу**, а не по имени.

Алгоритм автоопределения (реализуй именно так):

| Роль | Как опознать |
| --- | --- |
| `GLOBALS` | файл в `gfx/`, содержит подстроку `<TimedProductionList` |
| `ICONS` | файл в `gfx/`, содержит подстроку `productionType="` |
| `SKILLPOINTS` | файл в `skill/`, содержит `<skillPoint ` |
| `COLLECTIONS` | файл в `collections/`, содержит `<collections>` |

Если подходящих файлов ноль или больше одного — команда падает с внятной ошибкой и требует явной опции. Молча брать первый попавшийся — запрещено.

---

## 3. Новые файлы против старых в `docs/references/`

В проекте уже лежали старые копии. Они **устарели**.

| Старый файл | Новый файл | Разница |
| --- | --- | --- |
| `globals.xml` (4 486 811 б) | `gfx/gfx_settings_a742ef6a.xml` (4 503 806 б) | было 70 списков → стало **71**; было 2887 `<Buff>` → стало **2916** |
| `icons.xml` (1 037 442 б) | `gfx/gfx_settings_256c6645.xml` (1 041 660 б) | было 68 производителей → стало **69** (добавился `CommandCenter`, тип 72) |
| `collections.xml` (54 356 б) | `collections/collectibles_and_loot.xml` (54 356 б) | идентичен |
| — отсутствовал — | `skill/science_system_skillPoints.xml` | **НОВЫЙ.** Был блокером типа 2 |
| `en_lang.xml` (5.4 МБ, только EN) | `loca/localization_oasis.xml` (8.1 МБ) | больше языков |

### Действие для агента

1. Положи новый архив в `docs/references/xml/`.
2. **Не удаляй** старые `globals.xml` / `icons.xml` — на них ссылаются старые спеки и тесты.
3. Добавь в `docs/references/README.md` раздел с таблицей выше, чтобы следующий агент не перепутал версии.
4. Переключи дефолтные пути импортёра на новую папку.
5. Перегенерируй каталог и проверь дифф: единственные ожидаемые изменения — добавление, не удаление.

---

## 4. Форматы узлов — точные имена атрибутов

Это самый важный раздел документа. Атрибуты в разных узлах **называются по-разному, хотя значат одно и то же**. Это главная причина ошибок.

### 4.1. `<Buff>` — пул бафов (GLOBALS)

```xml
<Buff id="2" name="ProductivityBuffLvl3" tradable="true" deletable="true"
      buffType="Timed" produceable="true" sortIndex="3" group="0"
      productionTime="1800" productivityInputPercent="100"
      productivityOutputPercent="200" targetZones="Home,Friend"
      targetType="Building" targetDescription="Workyard"
      instantBuildCosts="8">
  <Costs>
    <Cost name="Fish" count="120"/>
    <Cost name="Bread" count="60"/>
    <Cost name="Sausage" count="20"/>
  </Costs>
</Buff>
```

| Атрибут | Тип | Обязателен | Смысл |
| --- | --- | --- | --- |
| `name` | string | да | **Это и есть `type_string` для команды 91** |
| `produceable` | `"true"`/отсутствует | нет | Фильтр. Берём только `"true"` |
| `group` | int | да | Номер вкладки в UI. Используется для типов 6 и 11 |
| `productionTime` | int, секунды | да | Длительность одной штуки |
| `instantBuildCosts` | int | нет | Цена мгновенного завершения в самоцветах |
| `buffType` | string | нет | `Timed`, `Instant` и др. |
| `requiresQuest` | string | нет | Условие доступности |
| `requiresEvent` | string | нет | Условие доступности |

Стоимость: вложенный `<Costs><Cost name= count=/></Costs>`.

Цифры: всего `<Buff>` — 2916; с `produceable="true"` — **299**; из них с блоком `<Costs>` — **248**. Остальные 51 — без явной стоимости (см. `pitfalls.md` P-33).

### 4.2. `<TimedProduction>` — явные списки (GLOBALS)

```xml
<TimedProductionList id="18" type="simpleproduction">
  <TimedProduction duration="255600" instantFinishCost="225" group="58"
                   name="ProductivityBuffLvl10">
    <Costs>
      <Cost name="Sausage" count="1500"/>
      <Cost name="Bread" count="1500"/>
    </Costs>
    <Effects>
      <reward type="Buff" item="ProductivityBuffLvl10" amount="1"/>
    </Effects>
  </TimedProduction>
</TimedProductionList>
```

Здесь длительность — `duration`, а цена мгновенного завершения — `instantFinishCost`. **Другие имена, чем у `<Buff>`.**

Атрибут `type` у списка принимает значения: `culturebuilding`, `simpleproduction`, `timedproduction`; если атрибута нет — считается `hardcoded`.

### 4.3. `<MilitaryUnit>` — военные юниты (GLOBALS)

```xml
<MilitaryUnit id="1" type="Recruit" combatantType="Defense" hitPoints="40"
              sequencePrio="1" hitPercentage="80" hitDamage="30" missDamage="15"
              produceable="true" productionTimeSeconds="180" xpForDefeat="2"
              instantBuildCosts="2" isElite="true|отсутствует">
  <Costs>
    <Cost name="Population" count="1"/>
    <Cost name="Beer" count="5"/>
    <Cost name="BronzeSword" count="10"/>
  </Costs>
</MilitaryUnit>
```

| Атрибут | Смысл |
| --- | --- |
| `type` | **Это `type_string` для команды 91**, не `id`, не `name` |
| `produceable` | Фильтр, берём только `"true"` |
| `isElite` | Определяет тип производства: `true` → 8, иначе → 0 |
| `productionTimeSeconds` | **Третье имя длительности!** Не `duration`, не `productionTime` |
| `instantBuildCosts` | Цена мгновенного завершения |

**Атрибута `tier` у `<MilitaryUnit>` НЕТ.** Если ты его ищешь — ты на ложном пути, см. OQ-7.

Цифры: всего `<MilitaryUnit>` — 379; с `produceable="true"` — **16** (9 обычных + 7 элитных).

### 4.4. `<skillPoint>` — очки навыков (SKILLPOINTS)

```xml
<skillPoint id="Tome" instantFinishCost="200" resetCost="50">
  <productionLevel amountProduced="0" productionTime="259200">
    <cost name="SimplePaper" count="..."/>
    <cost name="Nib" count="..."/>
    <cost name="Coin" count="..."/>
  </productionLevel>
  <productionLevel amountProduced="20" productionTime="259200"> ... </productionLevel>
</skillPoint>
```

| Атрибут | Смысл |
| --- | --- |
| `skillPoint@id` | **`type_string` для команды 91.** Значения: `Manuscript`, `Tome`, `Codex` |
| `skillPoint@instantFinishCost` | Цена мгновенного завершения (одна на все уровни) |
| `productionLevel@amountProduced` | **Порог.** Сколько штук игрок уже произвёл, чтобы этот ценовой уровень включился |
| `productionLevel@productionTime` | Длительность, секунды |
| `cost@name`, `cost@count` | Стоимость. **Тег с маленькой буквы: `<cost>`, не `<Cost>`** |

Фактические данные:

| id | Ценовых уровней | productionTime | В часах |
| --- | --- | --- | --- |
| `Manuscript` | 10 (пороги 0, 10, 20, …, 90) | 216 000 | 60 ч |
| `Tome` | 5 (пороги 0, 20, 40, 60, 80) | 259 200 | 72 ч |
| `Codex` | 3 (пороги 0, 30, 60) | 302 400 | 84 ч |

Важно: `productionTime` одинаков на всех уровнях одного типа. С ростом уровня растёт только стоимость. Но не полагайся на это — читай атрибут поуровнево.

### 4.5. `<collection>` — коллекции (COLLECTIONS)

```xml
<collection name="ChristmasResourceCollection" pLvl="16"
            outBuffName="AddResource_ChristmasResourceCollection"
            outBuffResourceName="ChristmasResource"
            blueprint="" productionTime="3600" InstantBuildCosts="30"
            requiresEvent="XMAS2017plus_Content">
  <resource name="CollectibleChristmasBells" amount="25"/>
  <resource name="CollectibleSnowflake" amount="25"/>
</collection>
```

| Атрибут | Смысл |
| --- | --- |
| `name` | **Кандидат на `type_string`.** См. OQ-4 — это единственное место, где осталась неопределённость |
| `outBuffName` | Имя бафа, который получится на выходе |
| `productionTime` | Длительность, секунды |
| `InstantBuildCosts` | **Заглавная `I`!** У бафов было `instantBuildCosts` с маленькой |
| `pLvl` / `minLevel` / `maxLevel` | Уровень игрока. В реальных данных встречается `pLvl`; `minLevel`/`maxLevel` — только в закомментированном примере |
| `requiresEvent` | Условие доступности |
| `<resource name= amount=>` | Стоимость. **Тег `<resource>`, атрибут количества — `amount`, а не `count`** |

Цифры: 16 узлов `<collection>` внутри `<collections>`.

**Ловушка:** в шапке файла есть большой закомментированный пример `<collection name="CountrySaying" …>`. Он **не должен** попасть в каталог. Поэтому парсить надо XML-парсером (который игнорирует комментарии), а **не regex**. Если ты возьмёшь regex, ты получишь 17 коллекций вместо 16 и сломаешь тест.

### 4.6. `<Building>` — здания-производители (ICONS)

```xml
<Building name="Bookbinder" productionType="2" uiType="..." nofUpgrades="...">
```

Здание считается производителем тогда и только тогда, когда у него есть атрибут `productionType`. **Не используй `uiType` для этого** — это тупиковая гипотеза, уже опровергнутая.

Цифры: 69 зданий с `productionType`. Полный список — в `fixtures/producers.json`.

---

## 5. Локализация (опционально, вне scope этой задачи)

Файл `loca/localization_oasis.xml` (8.1 МБ, корень `<oasis>`) содержит человеческие названия. Сейчас UI показывает технические имена вида `ProductivityBuffLvl3`.

Соответствия ключей для будущей задачи:

| Сущность | Ключ локализации |
| --- | --- |
| Баф | `<Buff@name>` как есть |
| Очко навыка | `Skillpoint_<id>`, например `Skillpoint_Tome` |
| Здание | `<Building@name>` в группе `BUILDINGS` |
| Юнит | `<MilitaryUnit@type>` |

Не делай это сейчас. Зафиксируй как техдолг.

---

## 6. Эталонные фикстуры

В папке `fixtures/` лежат готовые JSON, извлечённые из этих XML:

| Файл | Содержимое |
| --- | --- |
| `skillpoints.json` | 3 типа очков со всеми ценовыми уровнями |
| `military_units.json` | 16 производимых юнитов с готовым `target_production_type` |
| `collections.json` | 16 коллекций |
| `buff_pool.json` | 299 производимых бафов |
| `producers.json` | 69 зданий-производителей |
| `timed_production_lists.json` | все 71 список |
| `summary.json` | агрегаты для быстрых ассертов |

Используй их в тестах как ожидаемые значения. Перегенерировать: `python3 tools/extract_sources.py docs/references/xml <out>`.
