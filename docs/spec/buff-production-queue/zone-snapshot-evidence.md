# Снапшот зоны: где лежит очередь производства (доказано трафиком)

Источник: `req.txt` от оператора, 2026-08-28. Восемь реальных вызовов
`POST https://r02-gs003.thesettlersonline.ru/GameServer/amf`, скопированных из
браузера («copy as fetch» + base64 ответа).

**Этот файл вместе с `protocol-evidence.md` имеет высший приоритет.** Он основан
на наблюдаемом трафике. При расхождении с `design.md` §4 верно то, что
здесь.

---

## 0. Главное: блокер этапа 0/2 ЗАКРЫТ

Вызов №3 в дампе — это полный снапшот зоны (595 147 байт, `dZoneVO`),
и в нём **есть непустая очередь производства**. Все гипотезы о ключах
снапшота из `design.md` §4 теперь заменены фактами. Ни одно из моих
предполагавшихся имён (`production_queues`, `production_type`,
`upgrade_level`) не совпало с реальными.

---

## 1. Очереди лежат НА ЗОНЕ, а не на зданиях

Путь: `body.data.data.timedProductions_vector`

Это `Array` из 12 элементов, каждый — `flex.messaging.io.ArrayCollection`
(externalizable), внутри список `dTimedProductionVO`. В дампе 11 очередей
пустые, одна (индекс 3) содержит один заказ:

```json
{
  "__class": "defaultGame.Communication.VO.dTimedProductionVO",
  "uniqueId": { "__class": "...dUniqueID", "uniqueID1": 46557, "uniqueID2": 0 },
  "playerId": 1601416,
  "productionType": 2,
  "type_string": "Tome",
  "amount": 1,
  "producedItems": 1,
  "collectedTime": 259200000.0,
  "modifiedProductionAdder": 0,
  "modifiedProductionMultiplier": 1.0,
  "modifiedInstantFinishCostAdder": 0,
  "modifiedInstantFinishCostMultiplier": 1.0,
  "stacks": 1,
  "index": 1,
  "buildingGrid": 0
}
```

Здесь же второе независимое подтверждение ADR-3-R: в снапшоте у VO те же
**14 полей** в том же порядке, что и в команде 91.

---

## 2. КРИТИЧНО: `buildingGrid = 0` в снапшоте

Заказ `Tome` — это Переплётчик (`productionType = 2`), который в зоне стоит
на `buildingGrid = 8998`. Но в самом заказе `buildingGrid = 0`.

Сравните с ответом на команду 91 из `protocol-evidence.md`, где
`buildingGrid = 9001`. Вывод: **`buildingGrid` — параметр вызова, а не часть
состояния заказа.** Сервер использует его, чтобы найти здание и взять
его `productionType`, но в очереди заказ хранится без привязки к гриду.

Это **третье независимое доказательство** того, что очередь общая на пару
(зона, `productionType`), а не собственная у каждого здания (первые два —
`cZone.GetProductionQueue(int)` и `cTimedProductionQueue`, см. `decisions.md`).

Прямое следствие для UI: фильтровать заказы по гриду выбранного здания
**невозможно**. Показывать только «занято в очереди типа N», без разбивки
по зданиям. Строка `tasks.production.shares_queue_warning` обязательна.

---

## 3. Как сопоставить очередь с `productionType`

Индекс в `timedProductions_vector` — ЭТО НЕ `productionType`. Проверка на дампе:
заказ с `productionType = 2` лежит в элементе с индексом 3.

В зоне 312 зданий, из них 11 — производители с уникальными типами. Порядок
первого появления типов при обходе `buildings`:
`[4, 1, 0, 2, 24, 27, 38, 67, 66, 18, 40]` — то есть тип 2 действительно стоит на
4-й позиции (индекс 3), и совпадение с наблюдаемым индексом есть.

**Но на это полагаться НЕЛЬЗЯ:**

- совпадение подтверждено на выборке из одного элемента;
- очередей 12, а уникальных типов зданий 11 — одна очередь лишняя и ни к
  какому зданию в зоне не привязана;
- у ПУСТОЙ очереди нет ни `productionType`, ни любого другого признака:
  `ArrayCollection` несёт только массив, без метаданных.

### Надёжный алгоритм (обязателен для `ZoneSnapshot::productionQueueFor()`)

```
для искомого типа T:
    для каждой коллекции Q в timedProductions_vector:
        если Q не пуста и Q[0].productionType == T:
            вернуть Q
    вернуть пустую очередь (0 заказов)
```

Алгоритм корректен без знания порядка: если очередь для типа T не найдена
среди непустых, значит заказов типа T нет, и ответ «0 занято» верен.
Никогда не индексировать массив по `productionType` напрямую. Новая ловушка
**P-26**.

---

## 4. Здание НЕ несёт `productionType` — каталог обязателен

Фактические поля `dBuildingVO` (32 шт., путь `body.data.data.buildings`):

```
playerID, buildingCreationTime, buildingName_string, skin, buildingGrid,
uniqueId, buildingMode, startWorkCounter, upgradeLevel, hitPoints,
lastRepairTime, recoveringHitPoints, initialSetOnXMLMap, isBought,
isProductionActive, armyVO, buffs, buildingProgress, upgradeIsInProgress,
upgradeStartTime, upgradeProgress, destructionTime, offsetX, offsetY,
origin, isEngagedInCombat, specialCombatPreviewVO, preCombatTipType,
minProductionLevel, campType, recurringChance
```

Выводы:

| Гипотеза `design.md` §4 | Факт |
| --- | --- |
| ключ `production_type` в снапшоте | **нет такого поля вообще** |
| ключ `upgrade_level` | есть, но имя `upgradeLevel` |
| ключ `production_queues` на здании | нет; очереди только на уровне зоны |
| ссылка здание → очередь | отсутствует в обоих направлениях |

Значит `productionType` берётся ИСКЛЮЧИТЕЛЬНО из статичного каталога
(`icons.xml` → `config/game_production.php`), сопоставление по
`buildingName_string`. Это подтверждает ADR-решение о генерации каталога и
делает команду `tso:import-production-catalog` обязательной, а не удобством.

Полезные поля, которые надо протащить в `ZoneSnapshot::producerBuildings()`:
`buildingName_string`, `buildingGrid`, `upgradeLevel`, `isProductionActive`,
`upgradeIsInProgress`, `uniqueId`, `minProductionLevel`.
Проверка `ProductionRejectionReason::BuildingUpgrading` теперь реализуема:
`upgradeIsInProgress == true`.

Примеры производителей из дампа:

| Здание | grid | `upgradeLevel` | `productionType` (из каталога) |
| --- | --- | --- | --- |
| ProvisionHouse | 9001 | 3 | 1 |
| Bookbinder | 8998 | 4 | 2 |
| AdventureBookbinder | 8205 | 1 | 24 |
| Barracks | 9180 | 3 | 0 |
| Mayorhouse | 8825 | 4 | 4 |
| SnackStand | 9808 | 1 | 18 |
| Prospector | 9532 | 1 | 66 |

Грид 9001 = ProvisionHouse — тот самый `buildingGrid` из ответа команды 91 в
`protocol-evidence.md`, а его `productionType = 1` совпадает с отправленным в
заказе. Связка «грид → имя здания → тип из каталога» проверена на живых
данных от конца до конца.

---

## 5. Очередь строительства — отдельная структура (P-6 подтверждён)

Путь `body.data.data.buildQueue`:

```json
{
  "__class": "defaultGame.Communication.VO.dBuildQueueVO",
  "maxCount": 3,
  "permanentSlotsCount": 0,
  "tempSlotsCount": 0,
  "buildings": [],
  "tempSlots": []
}
```

`maxCount = 3` совпадает с `cBuildQueue.mMaxCount = 3` из клиента. У очереди
производства аналога `maxCount` **нет** — сервер не сообщает лимит длины.
Поэтому P-18 остаётся в силе: лимит длины очереди не выдумывать, а
`ProductionRejectionReason::QueueFull` выставлять только по отказу сервера
(`errorCode != 0`), а не превентивно.

---

## 6. Смысл `producedItems` и `collectedTime`

Заказ `Tome`: `amount = 1`, `producedItems = 1`, `collectedTime = 259200000.0`
(= 259 200 000 мс = 72 часа). Переплётчик в `icons.xml` помечен
`waitForPickup="true"`.

Интерпретация: `producedItems` — сколько единиц уже готово, `collectedTime`
— временная метка/срок в миллисекундах, НЕ unix-время. Заказ в состоянии
«готов, ждёт забора»: `producedItems == amount`.

Открытый вопрос **OQ-5**: точная семантика `collectedTime` (относительно
`serverTime` или абсолютная игровая метка). Не блокирует реализацию: нам нужна
только длина очереди, а не тайминги. Для сравнения в том же `dZoneVO` есть
`serverTime`, `serverTimeStamp`, `lastGameTickRefreshTime`, `realmTimeOffset`.

---

## 7. Полезные секции `dZoneVO` (82 поля)

Прямо относятся к фиче:

| Поле | Назначение |
| --- | --- |
| `timedProductions_vector` | все очереди производства (главное) |
| `buildings` | 312 зданий, источник гридов и уровней |
| `resourcesVO` | остатки товаров — нужны для сверки стоимости (F-5) |
| `buildQueue` | очередь строительства, не путать |
| `zoneBuffs` | наложенные бафы зоны (в дампе пусто) |
| `serverTime`, `serverTimeStamp` | база для расчёта времён |
| `zoneOwnerPlayerID`, `zoneVisitorPlayerID` | чья зона |
| `requirements.timedProductionRequirements_vector` | требования к рецептам → может заменить `availability: unknown` |

Последняя строка — новый follow-up **F-10**: в `dRequirementListsVO` есть
отдельный `timedProductionRequirements_vector` (в дампе 4873 объекта
`dRequirementsVO` по всем спискам). Если там лежит разрешённость рецептов,
то ADR-8 («`availability: unknown`, не блокируем») можно будет усилить до
реальной проверки. Проверить на этапе 4, не блокер.

---

## 8. Таблица восьми вызовов из дампа

Все восемь ответов декодируются полностью, `errorCode = 0` везде,
`zoneID = 1601416` везде.

| № | target | Handler | Ответ `type` | Payload ответа | VO запроса |
| --- | --- | --- | --- | --- | --- |
| 1 | `/1/onResult` | — (пинг сессии) | — | `null` | `CommandMessage` + `DSMessagingVersion` |
| 2 | `/2/onResult` | `PlayerHandler` | 1014 | `dPlayerListVO` (друзья) | `dGetFriendsVO` |
| 3 | `/3/onResult` | `EventHandler` | **1002** | **`dZoneVO`** (595 КБ) | `dZoneLoginVO` |
| 4 | `/4/onResult` | `EventHandler` | 1062 | `dGameTickCommandVO` | — |
| 5 | `/5/onResult` | `TradeWindowHandler` | 1063 | `ArrayCollection` из `dTradeObjectVO` | — |
| 6 | `/6/onResult` | `GuildHandler` | 4014 | `null` | — |
| 7 | `/7/onResult` | `EventHandler` | 1005 | `null` | `dClientInitDataVO` |
| 8 | `/8/onResult` | `EventHandler` | 15000 | `dBlackMarketAuctionStateVO` | — |

### Важно про номера команд

В проекте `TsoAmfService::CMD_GET_ZONE = 1001`, а ответ пришёл с `type = 1002`.
То есть `dServerResponse.type` — это код ОТВЕТА, не код запроса. Сравните:
у команды 91 ответ тоже был `type = 91`, то есть совпадение кодов — не
правило. Новая ловушка **P-27**: `GameResponseValidator` не должен требовать
`response.type == отправленный cmd` в общем случае; для команды 91 равенство
проверено трафиком и допустимо.

---

## 9. Ограничение источника: запросы повреждены

Ответы пришли в base64 — байт-в-байт точные. Запросы пришли в виде
`fetch(...)` из DevTools, где тело — JS-строка. Бинарные байты > 0x7F, не
составлявшие валидный UTF-8, заменены на U+FFFD — по 5–13 байт на
запрос потеряно безвозвратно.

Потерянные байты попадают именно в числовые поля (`zoneID = 1601416` → байты
`E1 DF 08`, номера команд вида 1001 → `87 69`), поэтому **номера команд в
запросах из этого дампа не восстанавливаются**. Из запросов надёжно
читаются только ASCII-строки: имена классов, имена полей (traits),
handler, operation, токены.

Правило для будущих дампов (новая ловушка **P-28**): запросы тоже брать
в base64/бинарном виде, а не через «copy as fetch». Следствие: **OQ-4 остаётся
открытым** — точные значения `uniqueId`/`playerId` в ЗАПРОСЕ команды 91
по-прежнему неизвестны (команды 91 в этом дампе нет вообще).

---

## 10. Сводка правок к спеку

| Файл | Что меняется |
| --- | --- |
| `design.md` §4 | заменить гипотетические ключи на реальные: `timedProductions_vector`, `buildings[].buildingName_string/buildingGrid/upgradeLevel/upgradeIsInProgress`; убрать `production_type` из снапшота |
| `design.md` §5 | `productionQueueFor()` — поиск по `items[0].productionType`, не по индексу |
| `implementation-plan.md` этап 0 | ЗАКРЫТ полностью (3/3) |
| `implementation-plan.md` этап 2 | разблокирован, фикстура зоны есть |
| `pitfalls.md` | добавить P-26 (индекс ≠ тип), P-27 (`response.type` ≠ cmd), P-28 (как снимать дампы) |
| `requirements.md` | INV: очередь общая по `productionType` — теперь доказано, а не выведено из клиента |
| `verification.md` §4 | `ZoneParserProductionTest` строится на `call03_response.bin` |
| `oq-resolutions.md` | + OQ-5 (`collectedTime`), F-10 (`timedProductionRequirements_vector`) |

---

## 11. Где лежат артефакты

См. `references/README.md` в этом же архиве: восемь пар запрос/ответ в
бинарном, base64 и декодированном виде + схема `dZoneVO`. Их нужно
положить в `docs/references/amf/` как постоянный референс для всех будущих
задач, не только для этой фичи.
