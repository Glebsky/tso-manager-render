# Data sources: build_mine / upgrade_mine

Документ фиксирует, откуда взят каждый факт протокола, и — отдельно — чего в источниках
нет. Все ссылки проверяемы: указаны файлы и номера строк.

Файлы-референсы лежат в `docs/references/`:
`client_scripts.txt` (21.5 МБ, декомпилированные скрипты клиента), `globals.xml` (4.5 МБ,
игровые определения), `icons.xml` (1 МБ, определения зданий с числовыми id),
`userscripts/` (рабочие пользовательские скрипты для клиента игры).

## 1. Слои данных и цена ошибки

| Слой | Источник | Кто использует | Цена ошибки |
| --- | --- | --- | --- |
| Номер здания | `config/game.php` (сгенерирован из `icons.xml`) | `ConfigMineCatalog` | **Критическая: построено не то здание, ресурсы потеряны** |
| Пара «руда → шахта» | `config/game.php` (сверено с `globals.xml`) | `MinePlacementPolicy` | Критическая: сервер отклонит или построит не туда |
| Залежи и здания зоны | ответ `GET_ZONE` (`1001`), разобранный `parse_zone.py` | политики, UI | Средняя: skip или лишний отказ |
| Очередь стройки | тот же снапшот зоны | `BuildQueueBudget` | Низкая: лишний no-op запрос |

## 2. Команда постройки — `SET_BUILDING_IN_GAME (50)`

Объявление константы:

```
client_scripts.txt:69401   SET_BUILDING_IN_GAME:int = 50
client_scripts.txt:69405   UPGRADE_BUILDING:int = 60
```

Место вызова в клиенте (`client_scripts.txt:521897–521948`):

```actionscript
case COMMAND.SET_BUILDING_IN_GAME:
case COMMAND.SET_BUILDING_BY_BUFF:
    this.mGeneralInterface.SendServerAction(
        cursor.GetEditMode(),
        global.buildingGroup.GetNrFromName(cursor.mLevelObject_string),
        cursorGrid,
        0,
        (buildByBuff ? SetBuildingVO.Init(buff.GetUniqueId()) : null)
    );
```

Отображение аргументов на поля пакета (`client_scripts.txt:323716–323725`):

```
SendServerAction(_arg_1, _arg_2, _arg_3, _arg_4, _arg_5)
    action.type    = _arg_2
    action.grid    = _arg_3
    action.endGrid = _arg_4
    action.data    = _arg_5
    SendMessagetoServer(_arg_1, mCurrentViewedZoneID, action)
```

**Вывод:** для команды `50` поле `type` содержит номер здания, `grid` — целевой грид,
`endGrid = 0`, `data = null` (не `null` только для постройки из баффа, что вне объёма).

### 2.1. Независимое подтверждение рабочим юзерскриптом

`docs/references/userscripts/user_drunken_miner.js` — работающий скрипт постройки и
апгрейда шахт. Он подтверждает форму пакета на практике:

```
user_drunken_miner.js:65    const CMD_BUILD   = 50;
user_drunken_miner.js:66    const CMD_UPGRADE = 60;
user_drunken_miner.js:937   game.gi.SendServerAction(CMD_BUILD, currentMapping.number, currentGrid, 0, null);
user_drunken_miner.js:966   game.gi.SendServerAction(CMD_UPGRADE, 0, grid, 0, null);
```

Важный отрицательный факт: скрипт **не вызывает** `SetPrePlaceBuildingGridPos` и никакой
«предварительной установки» перед командой. Значит двухфазного протокола нет — команда
самодостаточна.

## 3. Номера зданий: `icons.xml` = `buildingNumber`

В клиенте номер получается так (`client_scripts.txt:83019–83037`):
`GetNrFromName(name)` → `mGOListDictionary[name].mGfxResourceListNr`.

Статический источник этих номеров — `docs/references/icons.xml`, где у каждого здания
есть атрибут `id`. Совпадение подтверждено 6 из 6 сверкой с `buildMapping` юзерскрипта:

| Руда | `user_drunken_miner.js:36–41` | `icons.xml` | Шахта |
| --- | --- | --- | --- |
| `BronzeOre` | `36` | `icons.xml:4107 id="36"` | `BronzeMine` |
| `Coal` | `37` | `icons.xml:4108 id="37"` | `CoalMine` |
| `GoldOre` | `46` | `icons.xml:4117 id="46"` | `GoldMine` |
| `IronOre` | `50` | `icons.xml:4121 id="50"` | `IronMine` |
| `Salpeter` | `63` | `icons.xml:4135 id="63"` | `SalpeterMine` |
| `TitaniumOre` | `69` | `icons.xml:4141 id="69"` | `TitaniumMine` |

У всех шести в `icons.xml` указано `nofUpgrades="7"`, что совпадает с
`DM_MaxUpgradeLvl = 7` (`user_drunken_miner.js:7`) — отсюда максимальный уровень апгрейда.

Воспроизводимая проверка:

```bash
grep -oE '<Building id="[0-9]+" name="(Bronze|Coal|Gold|Iron|Titanium|Salpeter)Mine"' \
  docs/references/icons.xml
```

**Осторожно:** в `icons.xml` есть похожие устаревшие записи с другим регистром и другими
id: `id="78" name="Ironmine"`, `id="84" name="Goldmine"`, `id="88" name="Mine"`
(`icons.xml:4152`, `4158`, `4164`). Это НЕ современные шахты. Сравнение имён —
регистрозависимое.

Id остальных шахт (вне объёма, только для справки): `BronzeMineEndless = 353`,
`ArcticIronMine = 355`, `ArcticGoldMine = 564`, `EpicIronMine = 680`, `EpicGoldMine = 681`,
`EpicBronzeMine = 840`, `EpicCoalMine = 841`.

## 4. Пара «руда → шахта» в `globals.xml`

Ограничение задано атрибутом `restrictPlacingToDeposit`:

```xml
<Building name="IronMine" hitPoints="500" constructionDuration="300"
          InstantBuildCosts="6" restrictPlacingToDeposit="IronOre" movable="false">
```

| Шахта | Строка `globals.xml` | `restrictPlacingToDeposit` |
| --- | --- | --- |
| `BronzeMine` | `53794` | `BronzeOre` |
| `IronMine` | `55863` | `IronOre` |
| `GoldMine` | `56549` | `GoldOre` |
| `TitaniumMine` | `57608` | `TitaniumOre` |
| `CoalMine` | `57836` | `Coal` |
| `SalpeterMine` | `58078` | `Salpeter` |

Значения `restrictPlacingToDeposit` буквально совпадают с именами руд, которые
возвращает `dDepositVO.name_string`. Поэтому политика сравнивает имя залежи из снапшота
с полем каталога напрямую, без нормализации.

Атрибут `movable="false"` — причина, по которой перемещение шахт вне объёма.

## 5. Залежи в протоколе зоны

Описание объекта (`client_scripts.txt:54816–54860`):

```
class dDepositVO
    gridIdx:uint                 // грид залежи
    name_string:String           // имя руды: IronOre, Coal, ...
    amount:int                   // текущий остаток
    maxAmount:int                // исходный объём
    accessible:int               // Enums.DEPOSIT_ACCESSIBLE_TYPES
    emptied:uint
    refillable:Boolean
    depositGroupdId:int
```

Смежные объекты: `dDepositGroupVO` (`client_scripts.txt:54736`),
`dFoundDepositVO` (`client_scripts.txt:51994–52015`, результат разведки геологом).

В клиенте залежи живут в `mCurrentPlayerZone.mStreetDataMap.mDepositContainer`
(подтверждено юзерскриптами: `user_drunken_miner.js:909`, `:510`;
`user_buildlist.js:293`; `user_deposit.js:139`).

### 5.1. Как юзерскрипт определяет «можно строить»

```
user_drunken_miner.js:916   var oreName = deposit.GetName_string();
user_drunken_miner.js:917   var mapping = RESOURCES.buildMapping[oreName];   // руда есть в каталоге
user_drunken_miner.js:924   var bld = game.zone.GetBuildingFromGridPosition(grid);
user_drunken_miner.js:925   if (bld !== null) { ...skip... }                 // грид пуст
```

**Критически важный отрицательный факт:** поле `accessible` скрипт **не проверяет вообще**.
Неразведанная залежь просто не попадает в контейнер залежей. Поэтому основной критерий —
«залежь присутствует в снапшоте + грид пуст + остаток больше нуля», а `accessible`
используется только как дополнительный фильтр, если поле присутствует в снапшоте.

### 5.2. Расхождение внутри самого юзерскрипта (не копировать)

UI-список скрипта считает грид свободным при `!bld || bld.GetBuildingMode() === 1 || === 4`
(`user_drunken_miner.js:524`), а перед отправкой команды требует строго `bld === null`
(`:925`). Из-за этого часть строк в списке никогда не строится. В нашей реализации UI и
обработчик обязаны использовать одну политику (INV-4).

## 6. Очередь стройки

```
user_drunken_miner.js:899   mHomePlayer.mBuildQueue.GetQueue_vector().length   // занято
user_drunken_miner.js:900   mHomePlayer.mBuildQueue.GetTotalAvailableSlots()  // всего
user_drunken_miner.js:901   free = total - used
user_drunken_miner.js:907   if (free < 1) break;
user_drunken_miner.js:898   new TimedQueue(1000)                             // 1 команда в секунду
```

Клиент также умеет двигать очередь командами `BUILDQUEUE_MOVE_UP/MOVE_DOWN/REMOVE`
(`client_scripts.txt:462966`, `463066`, `463408`) — вне объёма.

## 7. Предпосылки апгрейда

```
user_drunken_miner.js:963
if (building.GetUIUpgradeLevel() < maxUpgradeLevel
    && building.IsBuildingInProduction()
    && building.IsUpgradeAllowed(true)) { ...send 60... }
```

Соответствие полям снапшота зоны (`parse_zone.py`, ветка `dBuildingVO`):
`GetUIUpgradeLevel()` → `upgradeLevel`, `IsBuildingInProduction()` → `isProductionActive`,
идущий апгрейд → `upgradeIsInProgress`. Полного аналога `IsUpgradeAllowed(true)`
(проверка ресурсов и правил) на сервере админки нет — поэтому отказ игрового сервера
по ресурсам считается нормальным исходом и логируется как skip.

## 8. Чего в снапшоте зоны сейчас НЕТ

`storage/app/parse_zone.py` (800 строк) не извлекает ни залежи, ни очередь стройки:

- `recursive_extract()` начинается на строке `184`;
- ветка `dBuildingVO` — строки `219–250` (поля `buildingName_string`, `buildingGrid`,
  `isProductionActive`, `upgradeLevel`, `buildingMode`, `uniqueId1/2`);
- ветки `dSpecialistVO` (`252`), `dPlayerVO` (`466`), `dZoneVO` (`495`);
- итоговый словарь результата — строки `769–794`, ключей `deposits` и `build_queue` там нет;
- единственное упоминание строки «deposit» — `738: elif 'depositorium' in bname_lower:`
  (расчёт лимита склада, к залежам не относится).

**Следствие:** без доработки парсера (шаг 1 плана реализации) фича не может работать.

## 9. Уже существующие в проекте предпосылки

| Что | Где | Статус |
| --- | --- | --- |
| `CMD_BUILD = 50` | `app/Services/TsoAmfService.php` | объявлена, не используется |
| `CMD_UPGRADE = 60` | `app/Services/TsoAmfService.php` | объявлена, не используется |
| `buildServerAction(type, grid, endGrid, data)` | `app/Services/TsoAmfService.php` | готова к переиспользованию |
| `sendServerCall(...)` | `app/Services/TsoAmfService.php` | готова к переиспользованию |
| Типы задач для геологов | `TaskType::SendGeologist` | разведка залежей уже автоматизирована |
| Подписи типов залежей в UI | `Tasks.vue:1505–1513`, `:1657–1665` | переиспользовать; **не путать** со схемой нумерации `AccountDetail.vue:1297–1305` |

## 10. Итог: что проверять при обновлении игровых файлов

1. Перекачать `icons.xml`, повторить grep из §3 и сверить шесть номеров с `config/game.php`.
2. Перекачать `globals.xml`, сверить `restrictPlacingToDeposit` из §4.
3. Для залежей и очереди стройки ничего сверять не нужно: они приходят в рантайме из зоны.
