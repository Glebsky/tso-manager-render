# Feature: collect_building — сбор наград кликом по зданию

Revision 2. Ревизия 1 (единственный режим `DESTRUCT_BUILDING`) устарела: она описывала
только остров-коллекции и была бы опасна для праздничных зданий.

## Одной фразой

Оператор выбирает конкретное здание на своём острове, и планировщик "нажимает" на
него так же, как это делает игровой клиент, — забирая либо коллекцию, либо
квестовый подарок (Christmas Tree, `FlyingHouse`, воздушные шары).

Отличие от существующего `collect_pickups`: тот собирает **все** коллекции подряд и
не умеет ничего, кроме коллекций. Здесь — **одно указанное здание** и **две разные
игровые механики**.

## Ключевое открытие ревизии 2

Клиентский `SelectBuilding(building)` делает две независимые вещи:

1. если здание является `cCollectibleBuilding` — отправляет `DESTRUCT_BUILDING (65)`;
2. **всегда** вызывает `BuildingSelectedGui(building)`, и если у здания есть активный
   квест-триггер `buildingselected`, отправляет `QUEST_TRIGGER (100)`.

Это **две разные команды с разной формой пакета и разным профилем риска**. Праздничные
здания идут по ветке 2, и снос там не участвует вообще.

## Доказательная база протокола

| Факт | Источник (`client_scripts.txt`) |
| --- | --- |
| `SelectBuilding()` всегда зовёт `mQuestClientCallbacks.BuildingSelectedGui(building)` | `321734` |
| `BuildingSelectedGui()` проверяет `ContainsTriggerCondition(TYPE_BUILDING, CONDITION_SELECTED, name, "buildingselected", …)` | `23062`–`23068` |
| При успехе: `SendServerQuestTrigger(SERVER_STACK_BUILDING_SELECTED, building.GetGrid())` | `23070` |
| `SendServerQuestTrigger` собирает `dServerAction{type = <stack>, data = <grid>}` и шлёт `COMMAND.QUEST_TRIGGER` в `mCurrentViewedZoneID` | `22601`–`22607` |
| `COMMAND.QUEST_TRIGGER = 100` | `69425` |
| `SERVER_STACK_BUILDING_SELECTED = 2`, `SERVER_STACK_GET_LATEST_QUEST_LIST = 4` | `62956`, `62958` |
| `TYPE_BUILDING = 1`, `CONDITION_SELECTED` | `62976` (блок `TYPE_*`) |
| `ACTION_BUILDING_SELECTED_string = "buildingselected"` | `74004` |
| Реестр триггеров: `"buildingselected" -> BuildingSelectedTrigger` | `495675` |
| `QUEST_TRIGGER_ERROR = 551` | `70486` |
| Пул квестов: `dQuestPoolVO.mQuestVO_vector` → `dQuestElementVO.mQuestDefinition.questTriggers_vector` → `dQuestDefinitionTriggerVO{type, condition, name_string, actionName_string, actionType_string, amount, triggerIdx}` | `57162`, `56436`, `56324` |
| Ветка коллекций: `cCollectibleBuilding extends DestroyOnClickBuilding` → `SendDestructBuildingCommand(this, "cCollectibleBuilding")` → `DESTRUCT_BUILDING = 65` | `77074`, `77243`, `69409` |
| Награда праздничного здания — лут-таблица, открывается `cMysteryBoxPanel` | `300034`, `306362`, `317138` |

### Подтверждение со стороны игровых данных

XML игрока для `FlyingHouse` (id `1467`, ui `residence`) рендерит иконку подарка по
условиям `questcompletable item="BuiBonus_FlyingHouse_Start"` и
`questrunning item="BuiBonus_FlyingHouse_Loop"`, награда —
`Loottable_FlyingHouseMysteryBox`. То есть «подарок раз в 7 дней» — это серверный
цикл квеста `BuiBonus_*_Loop`, а иконка над зданием — производная от состояния квеста.
**Нам не нужно считать кулдаун**: сервер сам решает, есть награда или нет.

### Что `data` действительно значит

В ветке 65 `dServerAction.data` — строка-тег источника клика (`"cCollectibleBuilding"`,
а также `"cConstructionInfoPanel"`, `"cWarehouseInfoPanel"`, `"cSetBuilding-…"`), сервер
тип цели по ней не проверяет. В ветке 100 `data` — **сам `grid`**, а поля `grid` и
`endGrid` остаются нулевыми. Формы пакетов несовместимы, поэтому переиспользовать один
метод AMF нельзя.

## Профиль риска по режимам

| Режим | Команда | Что будет при неверном `grid` | Требуется allow-list? |
| --- | --- | --- | --- |
| `collectible` | `65` | **здание игрока будет снесено безвозвратно** | **Да, обязательно (INV-1)** |
| `quest_trigger` | `100` | сервер игнорирует клик; максимум no-op и код `551` | Нет, достаточно мягкой проверки |

Эта таблица — причина того, что фича не сводится к «одному новому хендлеру с одним
полем»: два режима имеют разную цену ошибки и разные гарантии.

### Что дали игровые данные `globals.xml`

Выгрузка `globals.xml` (4.5 МБ) подтвердила и одновременно ограничила наши гипотезы:

- Получен **точный каталог 22 имён** `Collectible*Building` — регулярки в allow-list
  можно заменить точным списком (закрывает Q-1 без `collections.xml`).
- Атрибут `destroyOnClick="true"` в игровых данных существует, но стоит всего у 10
  служебных объектов (`DummyBuilding*`, `Tracks`, ворота `tmc_buffad_*`) и **не стоит**
  у островных коллекций. Использовать его как источник allow-list нельзя.
- `FlyingHouse` описан как обычное здание (`hitPoints="1000"`, стоимость переноса,
  бонусы апгрейда) — **никакого статического признака «отдаёт подарок» в данных нет**.
- Награда существует отдельной сущностью: `Buff id="703" name="Loottable_FlyingHouseMysteryBox"`,
  один из 123 `Loottable_*`; связи «здание → лут-таблица» в данных нет.

Вывод для архитектуры: статические данные годятся как **каталог имён и подписей**, но
ответ на вопрос «есть ли подарок прямо сейчас» может дать только пул квестов аккаунта.
Поэтому отображение доступности (FR-11) строится в рантайме. Подробности и полные
списки — в `data-sources.md`.

## Что НЕ входит в объём

- Новые экраны и компоненты UI (ADR-11): переиспользуется существующая модалка выбора
  зданий; в неё добавляются только бейдж типа/доступности и один чекбокс-фильтр (FR-11).
- Изменения `collect_pickups`, `parse_zone.py`, схемы БД и существующих эндпоинтов.
- Друзья (`target_scope = friend`): квест-триггеры работают только в своей зоне.
- Автоматическое «нажать на всё подряд»: это уже покрыто `collect_pickups`.
- Разбор содержимого лута (что именно выпало из mystery box).

## Карта документов

| Файл | Содержание |
| --- | --- |
| `requirements.md` | Требования FR, инварианты INV, критерии приёмки AC |
| `design.md` | Архитектура, новые классы, форма пакетов, изменения UI |
| `decisions.md` | ADR с альтернативами и последствиями |
| `implementation-plan.md` | Поэтапный план с гейтами |
| `verification.md` | Матрица покрытия и сценарии проверки |
| `data-sources.md` | Что извлечено из `globals.xml` и пула квестов, точные списки имён |
