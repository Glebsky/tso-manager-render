# Data Sources: доказательства формата лотов рынка

> ПРАВИЛО. Это единственный разрешённый источник протокольных утверждений для
> фичи `market-tradeables`. Если ты собираешься написать в коде имя поля,
> число или строковую константу, которой нет в этом файле — СТОП. Найди
> доказательство и допиши сюда. Угадывать протокол запрещено
> (`docs/spec/constitution.md` §7).

Все ссылки на строки — для файлов в `docs/references/` на момент составления
спека. `client_scripts.txt` — декомпилированный ActionScript клиента игры
(21.5 МБ, 526k+ строк, CRLF).

---

## 1. Транспорт: откуда мы берём лоты

| Что | Где в нашем коде | Что делает |
| --- | --- | --- |
| AMF-вызов | `app/Services/TsoAmfService.php:214` `getMarketOffers()` | команда `1061`, `TradeWindowHandler.GetAvailableOffers` |
| Извлечение объектов | `storage/app/parse_market.py` | рекурсивно вытаскивает все объекты класса `dTradeObjectVO` и сериализует ВСЕ их атрибуты в JSON |
| Разбор | `app/Services/Market/Sync/MarketOfferParser.php` | текущий (сломанный) разбор строки лота |
| Запись | `app/Services/Market/Sync/MarketOfferPersister.php` | upsert в `market_offers`, insert в `market_history` |

Важно: `parse_market.py` уже отдаёт в PHP поле `type` и `slotType`. Ничего
дополнительно с игры тянуть не надо — данные УЖЕ есть, парсер их выбрасывает.

### 1.1. Поля `dTradeObjectVO`

**Источник:** `client_scripts.txt:51105` (объявление класса, пакет
`Communication.VO.TradeWindow`), `51132` (`readExternal`).

Порядок чтения в `readExternal`: `id`, `senderID`, `receiverID`, `type`,
`slotType`, `slotPos`, `created` (double), `remainingTime` (double),
`offer` (UTF), `lotsRemaining`, `deleted` (double), `removed`,
`offerAcceptedID`, `senderName` (UTF), `coolDownTime` (double),
`isTradeCancled` (bool).

Нам нужны: `id`, `senderID`, `senderName`, `type`, `slotType`, `created`,
`offer`, `lotsRemaining`.

---

## 2. ГЛАВНОЕ: как устроена строка `offer`

**Источник:** `client_scripts.txt:471794` — класс `ServerState.cTradeObject`;
метод `init(_arg_1:String, _arg_2:int)` на строке **471852**;
`parseResourceVO` — **471903**; `parseBuffVO` — **471926**.

Дословная логика `init` (`_arg_1` = строка лота, `_arg_2` = `type`):

```actionscript
var _local_3:Array = _arg_1.split("|");
if (_local_3.length != 3) { return (this); }      // РОВНО три сегмента

if ((_arg_2 == TRADE_RES_FOR_RES) || (_arg_2 == TRADE_RES_FOR_BUFF))
    this.offer = this.parseResourceVO(_local_3[0].split(","));
else if ((_arg_2 == TRADE_BUFF_FOR_RES) || (_arg_2 == TRADE_BUFF_FOR_BUFF))
    this.offer = this.parseBuffVO(_local_3[0].split(","));

if (_local_3[1] == "@") { this.costs = null; }
else if ((_arg_2 == TRADE_RES_FOR_RES) || (_arg_2 == TRADE_BUFF_FOR_RES))
    this.costs = this.parseResourceVO(_local_3[1].split(","));
else if ((_arg_2 == TRADE_RES_FOR_BUFF) || (_arg_2 == TRADE_BUFF_FOR_BUFF))
    this.costs = this.parseBuffVO(_local_3[1].split(","));

this.totalLots = gMisc.ParseInt(StringUtil.trim(_local_3[2]));
```

### 2.1. Значения `TRADE_TYPE`

**Источник:** `client_scripts.txt:73925-73929` (`Enums.TRADE_TYPE`).

| Константа | Значение | offerSide | costSide |
| --- | --- | --- | --- |
| `TRADE_RES_FOR_RES` | 0 | ресурс | ресурс |
| `TRADE_RES_FOR_BUFF` | 1 | ресурс | баф |
| `TRADE_BUFF_FOR_RES` | 2 | баф | ресурс |
| `TRADE_BUFF_FOR_BUFF` | 3 | баф | баф |
| `TRADE_ACCEPT` | 4 | (служебный, в списке офферов не встречается) |

### 2.2. Ресурсная сторона

**Источник:** `client_scripts.txt:471903-471912`.

```actionscript
private function parseResourceVO(_arg_1:Array):dResourceVO {
    var _local_2:dResourceVO = new dResourceVO();
    _local_2.name_string = _arg_1[0];
    _local_2.amount = gMisc.ParseInt(_arg_1[1]);
    return (_local_2);
}
```

Формат: `name,amount`. Ровно два поля.

### 2.3. Бафовая сторона

**Источник:** `client_scripts.txt:471926-471960`.

```actionscript
private function parseBuffVO(_arg_1:Array):dBuffVO {
    var _local_2:dBuffVO = new dBuffVO();
    _local_2.buffName_string = _arg_1[0];
    _local_2.resourceName_string = (_arg_1.length > 1) ? _arg_1[1] : "";
    if (_arg_1.length > 2) {
        _local_2.amount = gMisc.ParseInt(_arg_1[2]);
        if (_arg_1.length > 3) _local_2.recurringChance = gMisc.ParseInt(_arg_1[3]);
    } else {
        var d:cBuffDefinition = cBuff.getBuffDefinitionByName(_local_2.buffName_string);
        _local_2.amount = (d != null) ? d.GetAmount() : 0;
        _local_2.recurringChance = 0;
    }
    return (_local_2);
}
```

Формат: `buffName,resourceName,amount[,recurringChance]`. Поля 2..4
необязательны. Поля `dBuffVO` — `client_scripts.txt:54115`.

### 2.4. Приключение — это баф с именем `Adventure`

**Источники:**
- `client_scripts.txt:523676` `public static const ADVENTURE_BUFF:String = "Adventure";`
- `client_scripts.txt:523705-523707` — `BUILD_BUILDING_BUFF_ID = 20`,
  `ADVENTURE_BUFF_ID = 22`, `HIRED_MILITARY_BUFF_ID = 52`
- `client_scripts.txt:310630-310642` — при построении списка торгуемых сущностей
  клиент создаёт `dBuffVO` с `buffName_string = defines.ADVENTURE_BUFF` и
  `resourceName_string = <имя приключения>`, `amount = 0`
- `client_scripts.txt:7629-7637` — `cBuffDefinition.IsTradable()` для
  `ADVENTURE_BUFF_ID` спрашивает `cAdventureDefinition.IsTradable()`

Значит на проводе приключение выглядит как
`Adventure,<AdventureName>,<amount>` — например `Adventure,MadHenry,0`.
Имя приключения (`MadHenry`, `TheDarkBrotherhood`) — это `resourceName`,
а `Adventure` — только маркер вида.

**Именно поэтому в нашем рынке все приключения слились в один псевдоресурс
`Adventure`.**

### 2.5. Постройка — баф `BuildBuilding`

`client_scripts.txt:523684` и `523685`:
`BUILD_BUILDING_BUFF = "BuildBuilding"`,
`BUILD_DEFENSE_MODE_BUILDING_BUFF = "BuildDefenseModeBuilding"`.
Субъект — имя набора графики постройки (`client_scripts.txt:310648-310655`).

---

## 3. Почему бафовых лотов не видно ВООБЩЕ

Текущий код (`MarketOfferParser.php`, строки 40-62):

```php
$sellParts = explode(',', $parts[0]);
if (count($sellParts) < 2) { continue; }
$itemId = $sellParts[0];
$amount = (int) $sellParts[1];      // (int) 'MadHenry' === 0
...
if ($amount <= 0 || $targetAmount <= 0) { continue; }   // лот выброшен
```

Для лота `Adventure,MadHenry,0|Coin,150000|1`:

1. `type` не читается вообще;
2. `$itemId = 'Adventure'`;
3. `$amount = (int) 'MadHenry' = 0`;
4. `continue` — лот молча исчезает.

Аналогично для `FillDeposit,Fish,100|...` → `(int) 'Fish' = 0` → выброшен.
Лоты, где бафовая сторона — это цена (`type = 1`), выбрасываются по
`$targetAmount <= 0`.

Побочные дефекты того же метода:

| Дефект | Строка | Последствие |
| --- | --- | --- |
| `count($parts) < 2` вместо `!== 3` | 36 | не валидируется формат, третий сегмент (`totalLots`) теряется |
| `$parts[1] === '@'` → `continue` | 48 | лоты-заявки "куплю" полностью теряются |
| жёсткая секция `'RES'` | 66-67 | приключения/постройки никогда не переводятся |
| `(int)` от произвольной строки | 45, 57 | INV-1 нарушен, ошибки маскируются |

И в резолвере имён: `app/Services/Market/GameResourceNameResolver.php`,
`private const string SECTION = 'RES';` — единственная секция на весь рынок.

---

## 4. Как клиент локализует имя лота

**Источник:** `client_scripts.txt:6176-6210`, `cBuff.getLocalizedBuffName()`.

```actionscript
var _local_1:String = this.buffDefinition.GetType();
if (_local_1.indexOf(defines.ADVENTURE_BUFF) == 0)
    return GetText(LOCA_GROUP.ADVENTURE_NAME, this.resourceName_string);      // ADN
if (_local_1.indexOf(defines.BUILD_BUILDING_BUFF) != -1 || _local_1.indexOf("BuildDefenseModeBuilding") != -1)
    return GetText(LOCA_GROUP.BUILDINGS, this.resourceName_string);           // BUI
if (ProductivityBuff | SpeedUpPopulationGrowth | RecruitingBuff)
    return GetText(LOCA_GROUP.RESOURCES, _local_1);                           // RES по типу
if (AddResource | FillDeposit | HiredMilitary) {
    var key = (amount > 0) ? _local_1 : "";
    if (_local_1 == FILL_DEPOSIT_BUFF && resourceName == "") key += "Any";
    return GetText(LOCA_GROUP.RESOURCES, key, [this.amount, this.resourceName_string]);
}
if (ChangeColorScheme && resourceName) return GetText(RESOURCES, _local_1 + "_" + resourceName);
return GetText(LOCA_GROUP.RESOURCES, _local_1);
```

Ресурс: `client_scripts.txt:57919` `dResourceVO.getName()` →
`GetText(LOCA_GROUP.RESOURCES, name_string)`.

### 4.1. Коды секций локализации

**Источник:** `client_scripts.txt:71388-71428` (`Enums.LOCA_GROUP`).
Нужные нам: `BUILDINGS = "BUI"`, `RESOURCES = "RES"`, `LABELS = "LAB"`,
`ADVENTURE_NAME = "ADN"`.

### 4.2. Что уже есть в наших языковых файлах

Проверено в `lang/ru/game.php` (36051 ключей):

| Ключ | Результат |
| --- | --- |
| `RES.Adventure` | ОТСУТСТВУЕТ — вот почему в UI сырой `Adventure` |
| `RES.ProductivityBuffLvl1` | есть |
| `RES.FillDeposit`, `RES.FillDepositAny`, `RES.AddResource`, `RES.HiredMilitary` | есть |
| `ADN.MadHenry` | `Дикая Мери` |
| `ADN.WitchOfTheSwamp` | `Болотная ведьма` |
| `ADN.TheDarkBrotherhood` | `Темное братство` |

Секция `ADN` в `lang/ru/game.php` начинается на строке 1706 и содержит
216 записей. Импорт локализации уже тянет ВСЕ секции
(`app/Services/Lang/LangXmlParser.php` + `GameLangFileWriter.php`), поэтому
дополнительный импорт НЕ нужен — нужен только правильный выбор секции.

**Но:** экспорт для SPA ограничен списком
`LangFrontendExportCommand::FRONTEND_GAME_SECTIONS = ['BUI', 'LAB', 'RES', 'SPE']`
(`app/Console/Commands/LangFrontendExportCommand.php:22`). Секции `ADN` там нет —
это отдельная правка (см. `design.md` §6).

### 4.3. Механизм подстановок уже есть

`app/Services/Lang/GameTranslationResolver.php` поддерживает плейсхолдеры
`{N}` и `{N,SECTION}`, цепочку локалей `<locale> → en → fallback → сырой id`,
`MAX_PLACEHOLDER_DEPTH = 3`. То есть требование FR-5 с подстановками
`[amount, subject]` реализуемо существующим API `name(section, id, args)`.

---

## 5. Статические каталоги в XML

### 5.1. Приключения

`docs/references/xml/adventure.xml`, корень `<Adventures>`:

```xml
<Adventure id="1" levelRange="Middle" tradable="true" name="MadHenry"
           difficulty="8" quality="3" durationHours="..." type="..." />
```

Всего 189 элементов `<Adventure>`: `tradable="true"` — 79, `tradable="false"` — 110.
Ключ локализации = атрибут `name` (совпадает с ключами секции `ADN`).

### 5.2. Бафы

`docs/references/xml/gfx/gfx_settings_a742ef6a.xml`, элементы `<Buff>`:

```xml
<Buff id="3" name="FillDeposit_Fishfood" tradable="false" deletable="false"
      buffType="Instant" produceable="true" group="2" resourceName="Fish" amount="100" ... >
```

Всего 2916 элементов `<Buff>`, из них с `tradable="true"` — 363.

### 5.3. Что НЕ является источником вида лота

Атрибут `type="..."` внутри `<Item>` в лут-таблицах (`type="Buff"`,
`type="Resource"`, `type="Adventure"`) относится к наградам приключений, а НЕ к
рынку. Не путать с `dTradeObjectVO.type`. Это отдельная подсистема.

---

## 6. Типы слотов

**Источник:** `client_scripts.txt:73883-73893` (`Enums.TRADE_SLOT_TYPE`).

| Константа | Значение |
| --- | --- |
| `FREE_SLOT` | 0 |
| `PAID_SLOT_WITH_GEMS` | 1 |
| `PAID_SLOT_WITH_COINS` | 2 |
| `FRIEND_TO_FRIEND` | 4 |

Значение сохраняем в колонку `slot_type` как метаданные. Фильтрацию по нему в
этой фиче НЕ вводим (см. `decisions.md` ADR-6).

---

## 7. Чего у нас пока НЕТ (единственный блокер)

В `docs/references/amf/` есть дамп `call05` —
`TradeWindowHandler.getUserTradesHistory`. Живого дампа `GetAvailableOffers`
С БАФОВЫМ ИЛИ ПРИКЛЮЧЕНЧЕСКИМ ЛОТОМ нет. Все утверждения §2 получены из
кода клиента (уровень доверия 2), а не из трафика (уровень 1).

Что это значит для исполнителя: смотри `implementation-plan.md` этап 0.
Без живого дампа фича реализуема (декодер зеркалит клиент), но фикстура
должна быть добавлена, как только оператор снимет дамп.
