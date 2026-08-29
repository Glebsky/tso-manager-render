# Data Sources: протокольные факты

> **Правило.** Этот файл — единственный разрешённый источник протокольных
> утверждений для данной фичи. Любой факт здесь снабжён точной ссылкой на файл и
> строку. Если ты, агент, собираешься написать в коде число или имя поля, которого
> нет в этом файле — СТОП. Найди доказательство и допиши сюда, либо спроси оператора.
> Угадывать протокол запрещено (`docs/spec/constitution.md` §7).

Все ссылки на строки даны для файлов в `docs/references/` на момент составления
спека. Файл `client_scripts.txt` — декомпилированный ActionScript клиента игры
(21.5 МБ, 526k+ строк, CRLF).

---

## 1. Коды команд

**Источник:** `client_scripts.txt`, класс `COMMAND`, строки 69400–69500.

| Константа | Код | Строка | Назначение |
| --- | --- | --- | --- |
| `START_TIMED_PRODUCTION` | **91** | 69418 | **поставить заказ в очередь производства** |
| `STOP_PRODUCTION` | 107 | 69429 | тумблер «здание работает / не работает» |
| `DELIVER_PRODUCTION` | 141 | 69446 | забрать готовую продукцию |
| `PRODUCTION_MOVE_UP` | 1025 | 69487 | поднять заказ в очереди |
| `PRODUCTION_MOVE_DOWN` | 1026 | 69488 | опустить заказ |
| `PRODUCTION_REMOVE` | 1027 | 69489 | удалить заказ из очереди |
| `PRODUCTION_MOVE_TOP` | 1028 (`0x0404`) | 69490 | в начало очереди |
| `PRODUCTION_MOVE_BOTTOM` | 1029 | 69491 | в конец очереди |
| `PRODUCTION_CANCEL_ALL_WAITING` | 1030 | 69492 | отменить все ожидающие заказы |
| `APPLY_BUFF` | 61 | — | применить баф (уже реализовано в проекте) |

### 1.1. КРИТИЧНО: чем 91 отличается от 107

В проекте уже есть `TsoAmfService::startProduction()` и `stopProduction()`
(`app/Services/TsoAmfService.php:244–259`). **Обе шлют код 107** с разным
`dServerAction.type` (1 или 0):

```php
public function stopProduction(Account $account, int $grid): string
{
    $action = $this->buildServerAction(0, $grid, 0);
    return $this->sendServerCall($account, self::CMD_STOP_PRODUCTION, $action);
}

public function startProduction(Account $account, int $grid): string
{
    $action = $this->buildServerAction(1, $grid, 0);
    return $this->sendServerCall($account, self::CMD_STOP_PRODUCTION, $action);
}
```

Это **выключатель здания**, а не очередь. Подтверждение из рабочего юзерскрипта
`docs/references/userscripts/user_drunken_miner.js:61–64`:

```javascript
// COMMAND.STOP_PRODUCTION = 107
// SendServerAction(107, 0, grid, 0, null) -> stop production
// SendServerAction(107, 1, grid, 0, null) -> run production
const CMD_STOP_PRODUCTION = 107;
```

**Не переиспользуй эти методы и не расширяй их флагами.** Прецедент проекта —
`collect-building` ADR-3: два разных вызова с общим только словом «grid» получают
два явных метода, а не один с флагом.

---

## 2. Payload команды 91: `dTimedProductionVO`

**Источник:** `client_scripts.txt:59375–59420`, класс `Communication.VO.dTimedProductionVO`.

Полный набор полей класса:

```actionscript
public class dTimedProductionVO
{
    public var modifiedProductionMultiplier:Number = 1;
    public var collectedTime:Number;
    public var modifiedProductionAdder:int = 0;
    public var index:int;
    public var productionType:int;
    public var modifiedInstantFinishCostMultiplier:Number = 1;
    public var buildingGrid:int;
    public var uniqueId:dUniqueID;
    public var producedItems:int;
    public var amount:int;
    public var playerId:int;
    public var stacks:int = 1;
    public var modifiedInstantFinishCostAdder:int = 0;
    public var type_string:String;
}
```

### 2.1. Какие поля заполняет клиент при отправке

Клиент создаёт VO с нуля и заполняет **только 5 полей**. Проверено по всем четырём
местам вызова:

| Место | Строки | `amount` | `stacks` |
| --- | --- | --- | --- |
| `TimedProductionInfoPanel.Order()` (главная панель бафов) | 307959–307965 | `amountSlider.value` | `stackSlider.value` |
| `EffectTimedProduction.PlaceOrder()` | 286561–286567 | `1` | `1` |
| `SkillProductionPanel.Order()` | 302888–302892 | `1` | не задан |
| `CollectionsProduction.handleCreateCollectionCommand()` | 299753–299757 | `1` | не задан |

Эталонный (самый полный) вариант — `client_scripts.txt:307959–307965`:

```actionscript
var _local_2:dTimedProductionVO = new dTimedProductionVO();
_local_2.productionType = this.mProductionType;
_local_2.type_string   = this.mSelectedOrderType.GetType();
_local_2.amount        = this.mPanel.amountSlider.value;
_local_2.stacks        = this.mPanel.stackSlider.value;
_local_2.buildingGrid  = this.mBuilding.GetGrid();
this.mGI.mClientMessages.SendMessagetoServer(
    COMMAND.START_TIMED_PRODUCTION, this.mGI.mCurrentViewedZoneID, _local_2
);
```

### 2.2. Контракт для реализации

| Поле VO | Тип | Что писать |
| --- | --- | --- |
| `productionType` | `int` | `productionType` здания из `icons.xml` (см. §4) |
| `type_string` | `String` | системное имя рецепта, напр. `"ProductivityBuffLvl3"` |
| `amount` | `int` | количество единиц в заказе |
| `stacks` | `int` | количество стаков; **всегда `1`**, если не согласовано иное |
| `buildingGrid` | `int` | grid здания-производителя |

Остальные поля (`index`, `uniqueId`, `playerId`, `producedItems`, `collectedTime`,
все `modified*`) — **не отправлять**. Их заполняет сервер в ответе.

### 2.3. VO едет в `data` напрямую, БЕЗ обёртки `dServerAction`

Это отличие от всех существующих команд проекта. Сравни:

- Существующие команды (50, 60, 61, 107): `dServerCall.data = dServerAction{type, grid, endGrid, data}`.
  См. `TsoAmfService::buildServerAction()` — `app/Services/TsoAmfService.php:149–158`.
- Команда 91: `SendMessagetoServer(91, zoneID, dTimedProductionVO)` — третий аргумент
  идёт в `data` как есть. Обёртки `dServerAction` нет.

Следовательно новый метод сервиса **не должен** вызывать `buildServerAction()`.

### 2.4. Имя PHP-класса VO

`Amf3Encoder` (`app/Services/Amf/Amf3Encoder.php:63`) маппит алиасы по префиксу:

```php
if (str_starts_with($className, 'defaultGame_')
    || str_starts_with($className, 'Communication_')
    || str_starts_with($className, 'flex_messaging_')) {
```

Существующий VO называется `defaultGame_Communication_VO_dServerAction` для
AMF-алиаса `defaultGame.Communication.VO.dServerAction`. По аналогии новый класс:

```
app/Services/Amf/Vo/defaultGame_Communication_VO_dTimedProductionVO.php
```

**Правок в `Amf3Encoder` не требуется** — префикс `defaultGame_` уже поддержан.

---

## 3. `TIMED_PRODUCTION_TYPE`

**Источник:** `client_scripts.txt:73726–73743`.

```actionscript
public class TIMED_PRODUCTION_TYPE
{
    public static const MILITARY_UNIT:int = 0;
    public static const BUFF:int = 1;
    public static const SKILL:int = 2;
    public static const EFFECT:int = 3;
    public static const COLLECTIONS:int = 4;
    public static const RARE_BUFFS:int = 5;
    public static const COMBAT_THREE_WEAPONS:int = 6;
    public static const COMBAT_THREE_UNITS:int = 7;
    public static const ELITE_UNITS:int = 8;
    public static const COMBAT_THREE_WEAPONS_2:int = 11;
    public static const TYPE_HARDCODED:int = 10000;
    public static const TYPE_CULTUREBUILDING:int = 20000;
    public static const TYPE_SIMPLEPRODUCTION:int = 30000;
    public static const TYPE_TIMEDPRODUCTION:int = 40000;

    public static function isHardcoded(_arg_1:int):Boolean
    {
        return ((mapIdToType.getItem(_arg_1) == null)
            || (mapIdToType.getItem(_arg_1) == TYPE_HARDCODED));
    }
}
```

**Ловушка.** Значения 0–11 — это одновременно и «семантический вид продукции», и
`productionType` конкретных зданий (см. §4). Значения `10000`/`20000`/`30000`/`40000` —
метаклассификация, в VO они **не отправляются**.

---

## 4. Каталог зданий-производителей

**Источник:** `docs/references/icons.xml`, тег `<Building>`, атрибуты
`productionType`, `ui`, `uicontent`.

> **Важно.** В `globals.xml` атрибута `productionType` **НЕТ ВООБЩЕ** (0 вхождений).
> Не ищи его там. Единственный источник — `icons.xml`, 68 вхождений, все на теге
> `<Building>`.

Пример:

```xml
<Building id="87" name="Bookbinder" ui="timedproduction" uicontent="2"
          productionType="2" waitForPickup="true" showWaitForPickupIcon="true"
          waitForPickupSpriteIndex="13" nofUpgrades="6" ... />

<Building id="59" name="ProvisionHouse" nofUpgrades="7" ui="timedproduction"
          uicontent="1" productionType="1" ... />
```

### 4.1. Полный список (68 зданий)

`ui="timedproduction"` — 47 зданий, `ui="culture"` — 20, `ui="warehouse"` — 1.

| `productionType` | Здание | `ui` |
| --- | --- | --- |
| 0 | Barracks | timedproduction |
| **1** | **ProvisionHouse** | timedproduction |
| **2** | **Bookbinder** | timedproduction |
| 4 | Mayorhouse | warehouse |
| **5** | **ProvisionHouse2** | timedproduction |
| 6 | ExpeditionWeaponSmith | timedproduction |
| 7 | Barracks3 | timedproduction |
| 8 | EliteBarracks | timedproduction |
| 11 | ExpeditionWeaponSmith2 | timedproduction |
| 12 | SoccerField | culture |
| 13 | Meadhall | culture |
| 14 | Stronghold | timedproduction |
| 15 | Smokehouse | timedproduction |
| 16 | Laboratory | timedproduction |
| 17 | SpringPark | culture |
| 18 | SnackStand | timedproduction |
| 19 | EspionageBuilding | culture |
| 20 | ChristmasMarket_Player | culture |
| 21 | WeatherStation | culture |
| 22 | LoveGarden | culture |
| 23 | Observatory | culture |
| 24 | AdventureBookbinder | timedproduction |
| 25 | Harbour | timedproduction |
| 26 | Oilmill | timedproduction |
| 27 | ChristmasBakery | culture |
| 28 | LoveTree | culture |
| 29 | Chocolatier | timedproduction |
| 30 | OilRefinery | timedproduction |
| 31 | S4Lazaret | timedproduction |
| 32–36 | BlackTree_Blue / Gold / Green / Purple / Red | timedproduction |
| 37 | ConcertHall | culture |
| 38 | LoversStatue | timedproduction |
| 39 | BalloonMarket | timedproduction |
| 40 | StoneDepot | timedproduction |
| 41 | ToyFactory | timedproduction |
| 42 | Snowglobe | culture |
| 43 | Ostereierbaum | culture |
| 44 | CandyFactory | timedproduction |
| 45 | SiegeWorkshop | timedproduction |
| 46 | GrandFieldHospital | culture |
| 47 | ArtificerStudy | timedproduction |
| 48 | UnityEvent_Trophy | culture |
| 49 | OutdoorCanteen | culture |
| 50 | CarnivalGrounds | timedproduction |
| 51 | ArtMuseum | culture |
| 52 | PrintPress | culture |
| 53 | GiantTreeOfHope | culture |
| 54 | StarfallAirship | timedproduction |
| 55 | RomanticRestaurant | timedproduction |
| 56 | HoliFestivalGrounds | culture |
| 58 | GuildFestGrounds | timedproduction |
| 59–61 | GuildFestTentI / II / III | timedproduction |
| 62 | TowerOfBraggingRights | culture |
| 63 | IceSkatingLake | culture |
| 64 | CoffeeShop | timedproduction |
| 65 | Jewelcrafter | timedproduction |
| 66 | Prospector | timedproduction |
| 67 | ElderTreeLH | culture |
| 68 | WitchCoven | timedproduction |
| 69 | RetirementHome | timedproduction |
| 70 | WeddingChapel | timedproduction |
| 71 | FluffyLogistics | timedproduction |

> `productionType=3`, `9`, `10`, `57` в `icons.xml` отсутствуют. Это нормально,
> не пытайся их «восстановить».

### 4.2. Как здание получает `productionType`

`client_scripts.txt:83407`:

```actionscript
_local_12.productionType = _arg_5.GetAttributeInt("productionType", -1);
```

Значение по умолчанию `-1`. `client_scripts.txt:78279`:

```actionscript
if (_local_5.productionType >= 0) { /* создаётся/привязывается очередь */ }
```

**Следствие:** здание является производителем ⟺ `productionType >= 0`.
Здания без атрибута получают `-1` и очереди не имеют.

---

## 5. Владелец очереди производства: КОНТРИНТУИТИВНО

> **Читай внимательно.** Это место, где естественная гипотеза «у каждого здания
> своя очередь» неверна, и ошибка здесь даёт неправильный расчёт свободных слотов.

### 5.1. Очередь производства ≠ очередь строительства — ПОДТВЕРЖДЕНО

Это две независимые подсистемы:

| | Очередь строительства | Очередь производства |
| --- | --- | --- |
| Класс | `cBuildQueue` (`client_scripts.txt:283323`) | `cTimedProductionQueue` (`client_scripts.txt:483870`) |
| Владелец | `cPlayerData.mBuildQueue` — один на игрока | `cZone.productionQueue_vector` — вектор на зону |
| Слоты | `mMaxCount = 3` + permanent + temp (`463159`) | длина `mTimedProductions_vector` |
| Команды | `BUILDQUEUE_MOVE_UP/DOWN/REMOVE` = 115/116/117 | `PRODUCTION_MOVE_*` = 1025–1030 |

Заказ бафа **не занимает** слот стройки и наоборот.

### 5.2. Но очередь ключуется по `productionType`, а не по зданию

`client_scripts.txt:331321` — метод зоны:

```actionscript
public function GetProductionQueue(_arg_1:int):cTimedProductionQueue
{
    var _local_2:int;
    while (_local_2 < this.productionQueue_vector.length)
    {
        if (this.productionQueue_vector[_local_2].mProductionType == _arg_1)
        {
            return (this.productionQueue_vector[_local_2]);
        };
        _local_2++;
    };
    return (null);
}
```

`client_scripts.txt:78279–78285` — инициализация здания:

```actionscript
if (_local_5.productionType >= 0)
{
    _local_5.productionQueue = _arg_4.mCurrentPlayerZone.GetProductionQueue(_local_5.productionType);
    if (_local_5.productionQueue == null)
    {
        _local_5.productionQueue = new cTimedProductionQueue(
            _arg_4, _local_5.productionType, _local_5, ...
        );
    };
}
```

Читается так: здание **сначала ищет уже существующую** очередь своего
`productionType` в зоне и переиспользует её; создаёт новую только если такой нет.
Конструктор `cTimedProductionQueue` (`client_scripts.txt:483888`) при этом
регистрирует себя в зоне: `_arg_1.mCurrentPlayerZone.setProductionQueue(this)`.

**Вывод:** очередь одна на пару (зона, `productionType`).

### 5.3. Практические следствия

1. **Разные типы зданий — разные очереди.** ProvisionHouse (`1`), Bookbinder (`2`),
   Laboratory (`16`) имеют независимые очереди. Гипотеза оператора здесь верна.
2. **Два здания одного типа делят ОДНУ очередь.** Два ProvisionHouse (оба
   `productionType=1`) пишут в общую очередь. Второй экземпляр не удваивает слоты.
3. **Но `ProvisionHouse` (1) и `ProvisionHouse2` (5) — РАЗНЫЕ очереди**, несмотря
   на схожие имена. Так же `Barracks` (0) / `Barracks3` (7) / `EliteBarracks` (8).

Косвенное подтверждение п.2 — в клиенте есть локализационный ключ
`"DuplicatedBuildingTimedProductionTip"` (`client_scripts.txt:307464`), то есть
игра специально предупреждает игрока о дублирующем здании с общей очередью.

### 5.4. Что это значит для реализации

- Ключ идемпотентности/локов — `(account_id, productionType)`, **не** `(account_id, grid)`.
- Планируя 2 заказа на два одинаковых здания, оператор конкурирует сам с собой.
  UI должен это показывать (FR-11).
- `buildingGrid` в VO всё равно отправляется — сервер использует его, чтобы понять,
  какое здание физически производит (и применить его бонусы уровня/стакинга).

---

## 6. Каталог рецептов

Два разных механизма в зависимости от `productionType`. Не перепутай.

### 6.1. Механизм A: динамический список бафов (hardcoded типы)

Для «hardcoded» типов (0–11, у которых `mapIdToType` не задан) клиент строит список
рантаймом из всех бафов с `produceable="true"`, фильтруя по `group`.

**Источник рецептов:** `globals.xml`, тег `<Buff>`.

- Всего `<Buff>`: 2887
- С `produceable="true"`: **299**
- Из них с блоком `<Costs>`: **248** (у 51 затрат нет)

Пример полной записи:

```xml
<Buff id="2" name="ProductivityBuffLvl3" tradable="true" deletable="true"
      buffType="Timed" produceable="true" sortIndex="3" group="0"
      productionTime="1800" productivityInputPercent="100"
      productivityOutputPercent="200" targetZones="Home,Friend"
      targetType="Building" targetDescription="Workyard" instantBuildCosts="...">
    <Costs>
        <Cost name="Fish" count="120"/>
        <Cost name="Bread" count="60"/>
        <Cost name="Sausage" count="20"/>
    </Costs>
</Buff>
```

Значимые атрибуты:

| Атрибут | Смысл |
| --- | --- |
| `name` | **системное имя → `type_string` в VO** |
| `produceable` | `"true"` — можно заказать в производство |
| `group` | номер группы (таб в UI игры) |
| `productionTime` | секунды на 1 единицу |
| `instantBuildCosts` | гемы за моментальное завершение (нам не нужно) |
| `buffType` | `Timed` / `Instant` |
| `targetZones` | `Home` / `Home,Friend` |
| `targetType`, `targetDescription` | на что применяется (`Building`/`Deposit`, `Workyard`…) |
| `<Costs><Cost name count/>` | **стоимость в товарах за 1 единицу** |

### 6.2. Механизм B: явные `<TimedProductionList>`

Для не-hardcoded типов список задан явно в `globals.xml`, блок `<TimedProductions>`
(начинается ~смещение 2592120). Комментарий в самом файле:

```xml
<TimedProductions>
    <!-- These timed production lists will be used by buildings with ui=timedProduction.
         There must be set the productionList="" like this production list id here. -->
    <TimedProductionList id="0"> <!-- Military Units: defined by the produceable MilitaryUnits --> </TimedProductionList>
    <TimedProductionList id="1"> <!-- Buffs: defined by the produceable Buffs (produceable="true") --> </TimedProductionList>
    <TimedProductionList id="2"> <!-- Skillpoints: defined by the skill points in the skills.xml --> </TimedProductionList>
    <TimedProductionList id="3"> <!-- Effects --> </TimedProductionList>
    <TimedProductionList id="4"> <!-- Collectibles --> </TimedProductionList>
    <TimedProductionList id="5"> <!-- Rarity Provisioner -->
        <TimedProduction duration="600" instantFinishCost="10" group="1"
                         name="AddResource_ConvertPaperToMapparts">
            <Costs>
                <Cost name="SimplePaper" count="200"/>
                <Cost name="IntermediatePaper" count="150"/>
                <Cost name="Nib" count="40"/>
            </Costs>
            <Effects>
                <reward type="Buff" item="AddResource" name="MapPart" amount="50"/>
            </Effects>
        </TimedProduction>
    </TimedProductionList>
</TimedProductions>
```

**Ключевое наблюдение.** Списки `id=0..4, 6..8, 11` **ПУСТЫЕ** — там только
комментарии. Списки `id=5` (269 записей) и `id≥12` заполнены. Наполнение по id:

| id | Записей | Примеры |
| --- | --- | --- |
| 5 | 269 | `AddResource_ConvertPaperToMapparts`, `ProductivityBuffLvl103`, `FillDeposit_Hunter3` |
| 12 | 4 | `SpeedUpPopulationGrowth6`, `ProductivityBuffZoneOreLvl2` |
| 13 | 3 | `RecruitingBuffZoneLvl2`, `ProvisionerBuffZoneLvl1` |
| 14 | 3 | `RecruitingBuffLvl6..8` |
| 16 | 4 | `AddResource_ConvertFlourToGunpowder`, `AddResource_ConvertGoldToIron` |
| 24 | 3 | `AddResource_AdventureManuscript/Tome/Codex` |
| 29 | 3 | `ProvisionerBuffLvl4`, `BookbinderBuffLvl4` |
| 32–36 | по 7 | `ProductivityAreaBuff_BlackTree`, `ProductivityBuffEW4` |
| остальные | 1–17 | см. полный разбор при генерации конфига |

Атрибуты `<TimedProduction>`: `name`, `duration` (секунды, аналог `productionTime`),
`instantFinishCost`, `group`, `requiresEvent`, `requiresQuest`,
`requiresUpgradeLevelMin`, `requiresUpgradeLevelMax`, вложенные `<Costs>` и `<Effects>`.

### 6.3. Как клиент выбирает механизм

`client_scripts.txt:307936–307957` (внутри `TimedProductionInfoPanel`):

```actionscript
// ветка 1: явный список
for each (_local_6 in gMisc.iterableToArray(global.timedProductions_vector[this.mProductionType]))
{
    if (((((_local_6.GetGroup().toString() == _local_3)
        && (RequirementsHelper.checkRequirements(this.mGI, _local_6.requiresEvent, _local_6.requiresQuest)))
        && (this.mBuilding.GetUpgradeLevel() >= _local_6.requiresUpgradeLevelMin))
        && (this.mBuilding.GetUpgradeLevel() <= _local_6.requiresUpgradeLevelMax)))
    {
        if (BuffAdventureController.isActiveBuffAdventureBuffOrNormalBuff(_local_6.GetProductionName_string()))
        {
            _local_2.push([_local_6, this.GetAmountInProduction(_local_6), this.mBuilding]);
        };
    };
}
// ветка 2: динамический список бафов
else
{
    for each (_local_7 in cBuff.GetProduceableBuffDefinitions(this.mGI))
    {
        if (_local_7.GetGroup_string() == _local_3)
        {
            _local_2.push([_local_7, this.GetAmountInProduction(_local_7), this.mBuilding]);
        };
    };
}
```

> **НЕ ПРОВЕРЕНО.** Точное условие `if`, разделяющее ветки, в захваченном фрагменте
> отсутствует (строка выше 307936). По контексту и по `TIMED_PRODUCTION_TYPE.isHardcoded()`
> это почти наверняка `if (!TIMED_PRODUCTION_TYPE.isHardcoded(this.mProductionType))`.
> **Перед реализацией генератора каталога — дочитать `client_scripts.txt:307900–307940`
> и зафиксировать точное условие здесь.** Это единственный незакрытый протокольный
> вопрос в спеке.

### 6.4. Фильтры доступности рецепта (решение по вопросу оператора №2 — «как честнее»)

Оператор выбрал строгий вариант. Значит рецепт показывается, только если:

1. `group` рецепта входит в группы, поддержанные зданием;
2. `requiresUpgradeLevelMin <= уровень здания <= requiresUpgradeLevelMax`;
3. `requiresEvent` / `requiresQuest` пусты **или** выполнены.

Пункты 2–3 требуют уровня здания из снапшота зоны (см. §7) и знания активных
событий. Активные события из снапшота недоступны ⇒ см. `decisions.md` ADR-8:
рецепты с непустыми `requiresEvent`/`requiresQuest` помечаются
`availability: "unknown"` и показываются с предупреждением, а не скрываются.

---

## 7. Пробел в снапшоте зоны

`storage/app/parse_zone.py:221` извлекает из здания только:

```python
for attr in ['buildingName_string', 'buildingGrid', 'isProductionActive', ...]:
```

`app/Services/ZoneParserService.php` не содержит ни одного упоминания
`production` или `buff` (проверено grep — 0 совпадений).

**Отсутствует всё нужное:**

| Нужно | Зачем |
| --- | --- |
| `productionType` здания | ключ очереди; отбор зданий-производителей |
| уровень здания (`GetUpgradeLevel`) | фильтр `requiresUpgradeLevelMin/Max` |
| содержимое `productionQueue_vector` зоны | сколько заказов уже стоит |
| остатки товаров на складе | проверка «хватает ли на заказ» |

Следствие: **расширение парсера зоны входит в обязательный объём фичи**
(оператор подтвердил). См. `implementation-plan.md` этап 2.

---

## 8. Локализация имён

Системные имена (`ProductivityBuffLvl3`, `Bookbinder`) нечитаемы для оператора.
Юзерскрипты берут переводы так (`user_drunken_miner.js`):

```javascript
loca.GetText("RES", buffName)   // ресурсы и бафы
loca.GetText("BUI", buiName)    // здания
loca.GetText("LAB", 'ProductionStatus')
```

Соответствующий источник в репозитории — `docs/references/en_lang.xml` (5.2 МБ).
В проекте уже есть подсистема импорта языков (`app/Services/Lang/`,
команды `tso:lang:import` / `tso:lang:export-frontend`) — переиспользовать её,
а не писать свой парсер. Ср. `collect-building` follow-up F-8, где та же задача
оставлена открытой; здесь её можно закрыть.

---

## 9. Сводная таблица: что откуда брать

| Данные | Файл | Как достать |
| --- | --- | --- |
| Код команды 91 | `client_scripts.txt:69418` | зафиксировано выше |
| Поля VO | `client_scripts.txt:59375–59420` | зафиксировано выше |
| Эталонный вызов | `client_scripts.txt:307959–307965` | зафиксировано выше |
| `productionType` зданий | `icons.xml` | `<Building productionType="N">`, 68 шт. |
| Рецепты-бафы | `globals.xml` | `<Buff produceable="true">` + `<Costs>` |
| Рецепты явных списков | `globals.xml` | `<TimedProductionList id="N">/<TimedProduction>` |
| Владелец очереди | `client_scripts.txt:331321`, `78279` | зафиксировано выше |
| Переводы | `en_lang.xml` | через `app/Services/Lang/` |
