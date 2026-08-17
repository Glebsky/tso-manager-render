# Data sources: collect_building (revision 2)

Документ фиксирует, откуда берётся знание о кликабельных зданиях, что именно из
игровых данных проверено, и — отдельно — **чего в данных НЕТ**. Второе важнее
первого: именно отсутствие статического признака определило архитектуру FR-11.

## 1. Три слоя знания

| Слой | Источник | Кто использует | Цена ошибки |
| --- | --- | --- | --- |
| Каталог имён | `globals.xml` | Подписи и категории в UI | Низкая: неверная подпись |
| Allow-list ветки `65` | `config/game.php` | `ClickableBuildingRegistry` | **Критическая: снос здания** |
| Доступность сейчас | Пул квестов аккаунта | `QuestTriggerBuildingProvider` | Низкая: лишний no-op |

Слои не выводятся один из другого. Попадание имени в каталог не даёт права на
команду `65`, а отметка доступности не даёт права ни на что (INV-7).

## 2. Каталог коллекций из `globals.xml`

Извлечены все определения `<Building name="Collectible*">` — 22 имени:

```
CollectibleAdamantiumBuilding
CollectibleBannerBuilding
CollectibleBronzeCauldronBuilding
CollectibleCakeDoughBuilding
CollectibleCandlesBuilding
CollectibleChristmasBellsBuilding
CollectibleChristmasCandyBuilding
CollectibleChristmasGingerbreadBuilding
CollectibleEggpaintBuilding
CollectibleFoodCartBuilding
CollectibleFurs2Building
CollectibleFursBuilding
CollectibleGrainSacksBuilding
CollectibleHerbsBuilding
CollectibleKettleBuilding
CollectibleMagicStoneBuilding
CollectiblePlainEggBuilding
CollectiblePumpkinBuilding
CollectibleSacredStoneBuilding
CollectibleScarecrowBuilding
CollectibleWickerBasketBuilding
CollectibleWineBarrelBuilding
```

Точный машинный список перепроверяется командой:

```bash
grep -o '<Building name="Collectible[A-Za-z0-9_]*"' globals.xml \
  | sed 's/<Building name="//; s/"//' | sort -u
```

Важное наблюдение: все они объявлены однотипно и без каких-либо признаков
кликабельности:

```xml
<Building name="CollectibleHerbsBuilding" hitPoints="2000" destructionDuration="1" movable="false" />
```

То есть признак «это коллекция» живёт только в клиенте (класс `cCollectibleBuilding`),
а не в данных. Поэтому каталог — это список имён, а не вывод о поведении.

Сверка с реальной зоной оператора (743 здания): встретились `CollectibleHerbsBuilding`,
`CollectibleWineBarrelBuilding`, `CollectibleFursBuilding`, `CollectibleKettleBuilding`,
`CollectibleGrainSacksBuilding`, `CollectibleScarecrowBuilding`, `CollectibleBannerBuilding`,
`CollectibleFoodCartBuilding`, а также `StarfallStarDustMote` — все из каталога, кроме
последнего (событийный объект, `kind = Event`).

## 3. Чего в `globals.xml` НЕТ — и почему это важно

### 3.1. Нет признака «здание отдаёт подарок по клику»

`FlyingHouse` описан как совершенно обычное здание:

```xml
<Building name="FlyingHouse" hitPoints="1000" constructionDuration="10"
          InstantBuildCosts="10" tradable="false">
  <BuildingMoveCosts><Costs>
    <Cost name="RealPlank" count="100"/><Cost name="Marble" count="100"/>
  </Costs></BuildingMoveCosts>
  <BuildingUpgradeBonuses>…</BuildingUpgradeBonuses>
  <Blocks>…</Blocks>
</Building>
```

Ни атрибутов, ни вложенных тегов, связанных с подарком или кликом. Маркер над
зданием (`renderLayers` с `questcompletable` / `questrunning`) живёт в другом файле
игровых данных (описание зданий с `id`/`ui`/`filename`), а само условие — это
состояние квеста аккаунта, а не свойство здания.

**Следствие:** список «здания с подарками» в принципе невыводим из статики.
Отсюда рантаймовый `QuestTriggerBuildingProvider` (ADR-8, `design.md` §9).

### 3.2. Атрибут `destroyOnClick` есть, но он НЕ про коллекции

В файле ровно 10 зданий с `destroyOnClick="true"`:

```
DummyBuildingSpottedMushroom            tmc_buffad_open_gate_clickable_east
DummyBuildingCollectibleClue            tmc_buffad_closed_gate_clickable_east
DummyBuildingCollectibleClue_EpicResidence  tmc_buffad_open_gate_clickable_west
DummyBuildingCollectibleFurs2           tmc_buffad_closed_gate_clickable_west
DummyBuildingCollectibleObsidianShard   Tracks
```

Ни одного реального `Collectible*Building` в этом списке нет. Атрибут выглядит
идеальным кандидатом на роль allow-list (имя буквально совпадает с классом клиента
`DestroyOnClickBuilding`), но фактические данные говорят обратное.

Полагаться на него значило бы получить allow-list, который не разрешает ни одну
реальную коллекцию и при этом разрешает `Tracks` — то есть одновременно
бесполезный и опасный. Зафиксировано как ADR-13.

### 3.3. Нет связи «здание → лут-таблица»

Награда существует отдельной сущностью:

```xml
<Buff id="703" name="Loottable_FlyingHouseMysteryBox" buffType="Instant"
      targetZones="Home" targetType="Building" targetDescription="Mayorhouse" />
```

Всего в файле 123 записи `Loottable_*`, и `targetDescription` у них почти всегда
`Mayorhouse` (257 случаев) — то есть это техническая цель применения бафа, а не
здание-источник. Сопоставить подарок со зданием по этим данным нельзя;
совпадение подстроки (`FlyingHouse` в `Loottable_FlyingHouseMysteryBox`) — соглашение
об именовании, а не контракт. Поэтому разбор содержимого награды остаётся в
следующих шагах (F-4), а не в объёме этой фичи.

## 4. Кандидаты в праздничные здания (гипотеза, не контракт)

Имена из `globals.xml`, которые по смыслу похожи на «кликни и получи подарок»:
`FlyingHouse`, `BalloonMarket`, `BalloonMarket_mini`, `Christmastree_Deposit`,
`Christmas_Bench_Deposit`, `Christmas_Lantern_Deposit`, `Christmas_Nutcracker_Deposit`,
`Christmas_Presents_Deposit`, `Christmas_Pyramid_Deposit`, `GiftGhostShip`, `GhostLantern`,
`LoveTree`, `Snowglobe`, `ChristmasMarket_Player`, семейство `EW_Balloons_*`.

Этот список **нигде в коде не используется**. Он приведён только для подбора
тестовых целей на живом аккаунте (сценарий S-6). Зашивать его в код нельзя:
при каждом сезонном ивенте он устареет, а пул квестов — нет.

Фактические имена, подтверждённые пулом квестов оператора, вписываются сюда
после сценария S-6 (закрытие Q-2):

| Имя здания | Грид | Квест-триггер | Дата проверки |
| --- | --- | --- | --- |
| | | | |

## 5. Пул квестов как источник доступности

Запрос: `COMMAND.QUEST_TRIGGER (100)` с `dServerAction{type: 4, grid: 0, endGrid: 0, data: null}`
(`SERVER_STACK_GET_LATEST_QUEST_LIST`).

Разбор ответа:

```
dQuestPoolVO.mQuestVO_vector
  → dQuestElementVO.mQuestMode            // >= QUEST_MODE_DEACTIVATED — пропустить
  → dQuestElementVO.mQuestDefinition
      → questTriggers_vector
          → dQuestDefinitionTriggerVO{type, condition, name_string, actionName_string}
```

Фильтр: `type == TYPE_BUILDING (1)` и `condition == CONDITION_SELECTED`,
`actionName_string == "buildingselected"`. Имя здания — `name_string`.

Это ровно та ��е проверка, которую делает сам клиент перед отправкой триггера
(`ContainsTriggerCondition`, `client_scripts.txt:57393`; вызов — `23068`). Повторяя её,
мы получаем тот же ответ, который видит игрок в виде иконки над зданием.

Семантика ошибки: `forAccount()` возвращает `null` при любом сбое и никогда не
пустой массив. Пустой массив означает утверждение «подарков нет нигде», и путать
его с отказом связи нельзя (ADR-12).

## 6. Сопровождение

При обновлении игры:

1. Запросить новый `globals.xml`.
2. Пересчитать список `Collectible*Building` командой из §2.
3. Сравнить с allow-list в `config/game.php`. Новое имя добавляется только вручную
   и только после проверки, что это действительно коллекция.
4. Для праздничных зданий делать не нужно ничего: их список берётся из рантайма.

Пункт 4 — главный практический выигрыш выбранной архитектуры: новый ивент с
новыми подарочными зданиями начинает работать без релиза кода.
