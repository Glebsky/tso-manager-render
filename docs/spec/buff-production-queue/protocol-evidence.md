# Протокольные доказательства: реальный трафик команды 91

Источник: живой дамп от оператора, 2026-08-28. Ответ раскодирован полностью
(`/data/amf_decode.py`, минимальный AMF3-декодер с поддержкой traits,
ссылок и externalizable).

**Этот файл имеет высший приоритет во всём спеке.** Он основан на наблюдаемом
трафике, а не на чтении декомпилированного клиента. При любом расхождении
с `data-sources.md`, `design.md`, `decisions.md` или `verification.md` — верно то,
что написано здесь.

---

## 1. ГЛАВНОЕ: ADR-3 и AC-3 ОПРОВЕРГНУТЫ

### Было в спеке (неверно)

> VO должен иметь ровно 5 свойств (`productionType`, `type_string`, `amount`,
> `stacks`, `buildingGrid`), потому что клиент заполняет только их; лишние
> поля меняют тело AMF и сломают совместимость.

### Факт

В traits ЗАПРОСА (видно в полезной нагрузке) и в traits ОТВЕТА присутствуют
**все 14 полей** в одном и том же порядке:

```
uniqueId, playerId, productionType, type_string, amount, producedItems,
collectedTime, modifiedProductionAdder, modifiedProductionMultiplier,
modifiedInstantFinishCostAdder, modifiedInstantFinishCostMultiplier,
stacks, index, buildingGrid
```

Причина: Flash сериализует ВСЕ объявленные `public var` класса, а не только
те, что код присвоил. То, что в `TimedProductionInfoPanel.Order()` присваиваются
5 полей, НЕ значит, что в тело уйдут 5 полей — остальные уйдут с
значениями по умолчанию из объявления класса.

### Новое решение (ADR-3-R)

`app/Services/Amf/Vo/defaultGame_Communication_VO_dTimedProductionVO.php` ОБЯЗАН
объявлять все 14 свойств в точном порядке выше, с такими дефолтами:

| Поле | Тип в AMF | Значение в дампе | Что отправлять |
| --- | --- | --- | --- |
| `uniqueId` | object ‖ null | `dUniqueID{46853, 0}` (в ответе) | `null` — см. OQ-4 |
| `playerId` | int | `1601416` | `0` — см. OQ-4 |
| `productionType` | int | `1` | из payload |
| `type_string` | string | `EventMonsterBuffDrillManualRough` | из payload |
| `amount` | int | `1` | из payload, 1..25 |
| `producedItems` | int | `0` | `0` |
| `collectedTime` | **double** | `0.0` | `0.0`, не `0` |
| `modifiedProductionAdder` | int | `0` | `0` |
| `modifiedProductionMultiplier` | **double** | `1.0` | `1.0` |
| `modifiedInstantFinishCostAdder` | int | `0` | `0` |
| `modifiedInstantFinishCostMultiplier` | **double** | `1.0` | `1.0` |
| `stacks` | int | `1` | из payload, 1..200 |
| `index` | int | `0` | `0` (назначает сервер) |
| `buildingGrid` | int | `9001` | из payload |

**Критично про типы.** Три поля — `collectedTime`,
`modifiedProductionMultiplier`, `modifiedInstantFinishCostMultiplier` — кодируются
маркером `0x05` (double, 8 байт), а НЕ `0x04` (integer). В байтах дампа это
видно как `05 00 00 00 00 00 00 00 00` и `05 3F F0 00 00 00 00 00 00`. Если в PHP
объявить их `int`, энкодер выдаст `0x04` и тело разойдётся с эталоном.
В PHP это `float`, инициализация `= 0.0` и `= 1.0`.

### Правка к `verification.md` §2

Строка таблицы «Ровно 5 свойств; нет `index`, `uniqueId`, `playerId`,
`producedItems`, `collectedTime`» — **УДАЛИТЬ**. Замена:

| Ассерт | Покрывает |
| --- | --- |
| Ровно 14 свойств в traits, в точном порядке | AC-3-R |
| `collectedTime` и два `*Multiplier` закодированы маркером `0x05` | новый P-22 |
| `type_string` с подчёркиванием | P-9 |
| НЕТ подстроки `dServerAction` | AC-2 |

---

## 2. Форма запроса — AC-2 ПОДТВЕРЖДЁН трафиком

Из полезной нагрузки видна цепочка:

```
flex.messaging.messages.RemotingMessage
  source      = null
  operation   = "ExecuteServerCall"
  destination = "com.bluebyte.game.servlet.EventHandler"
  parameters  = [ defaultGame.Communication.VO.dServerCall ]
        type   = 91
        zoneID = <зона>
        data   = defaultGame.Communication.VO.dTimedProductionVO   <-- НАПРЯМУЮ
        dsoAuthUser, dsoAuthToken, dsoAuthRandomClientID
  headers = { DSEndpoint: "SMC-Endpoint", DSId: "cc7acf3e-..." }
```

Между `dServerCall.data` и `dTimedProductionVO` **нет обёртки `dServerAction`**.
Это подтверждает главное отличие команды 91 от всех остальных команд
проекта (50, 60, 107, 13002 идут через `buildServerAction()`) — то есть
`queueTimedProduction()` НЕ должен вызывать `buildServerAction()`. Гипотеза
из `design.md` §2 подтверждена без изменений.

`dsoAuthUser` / `dsoAuthToken` / `dsoAuthRandomClientID` уже заполняются
существующим `buildServerCall()` — нового кода не требуют.

---

## 3. Форма ответа — новые данные для `GameResponseValidator`

Полностью раскодированная структура (успешный заказ):

```
AMF0 envelope: version=3, headers=0, bodies=1
  target = "/48/onResult"     <-- счётчик вызова, не фиксирован
  declared_len = 0xFFFFFFFF   <-- длина НЕ указана, парсить до конца
  marker 0x11                 <-- переход в AMF3

flex.messaging.messages.AcknowledgeMessage
  body = dServerResponse
      type   = 91
      zoneID = 1601416
      data   = dServerActionResult
          clientTime = 19035967316.0     (double)
          errorCode  = 0                 <-- 0 = УСПЕШНО
          data       = dGameTickCommandVO
              playerID = 1601416
              time     = 19035970066.0   (double)
              mode     = 91
              data     = flex.messaging.io.ArrayCollection  (externalizable)
                  └─ [ dTimedProductionVO ]   <-- СОЗДАННЫЙ ЗАКАЗ
              uniqueID = null
  clientId      = "f085c500-69b3-4aad-90a9-50244d809a25"
  correlationId = "2b8aa25a-5752-4e3c-9123-8d04ea3dba39"
  messageId     = "031be631-53a2-416d-8acd-c23e3a15038a"
  timestamp     = 1787941166321.0
```

### Пути доступа для проверки успешности

| Что | Путь |
| --- | --- |
| Код ошибки | `body.data.errorCode` |
| Номер команды (сверка = 91) | `body.type` и `body.data.data.mode` |
| Созданный заказ | `body.data.data.data[0]` |
| Номер в очереди | `body.data.data.data[0].index` |
| Серверный id заказа | `body.data.data.data[0].uniqueId.uniqueID1` |

### Две ловушки в разборе ответа

**P-23. `ArrayCollection` — externalizable.** Её содержимое НЕ читается как обычные
свойства объекта. После traits с флагом externalizable идёт ОДНО вложенное
значение — массив (`0x09`). Декодер, который этого не делает, смещает все
последующие поля на одно и тихо выдаёт мусор без ошибки. Эта ошибка была
допущена при первом разборе этого же дампа — читалось, будто заказ лежит
в поле `uniqueID`. Обязательно проверить, как `ZoneParserService` и
`storage/app/parse_zone.py` обрабатывают externalizable — если так же, то это
существующий баг за пределами этой фичи.

**P-24. Поле `uniqueID` в `dGameTickCommandVO` НЕ содержит заказ.** Оно `null`.
Заказ лежит в поле `data`. Имена полей здесь вводят в заблуждение;
ориентироваться только по порядку traits.

### Новый follow-up F-9

Собрать таблицу `errorCode` для команды 91 (очередь переполнена, нет
ресурсов, рецепт недоступен, здание строится) и сопоставить с
`GameErrorResolver::getMessage()`. Пока известен только `0` = успех. Это же
закроет OQ-2 в части длины очереди (ручная проверка №6).

---

## 4. Ветка B подтверждена живым заказом

Заказ ушёл с `productionType = 1` и `type_string = EventMonsterBuffDrillManualRough`.
Этого имени НЕТ ни в одном `<TimedProductionList>` — оно есть только в пуле
бафов:

```xml
<Buff id="581" name="EventMonsterBuffDrillManualRough" sortIndex="1"
      tradable="true" deletable="false" productionTime="420" buffType="Instant"
      requiresQuest="WeeklyChallenge_Defence_Main_lvl26,...,WeeklyChallenge_Defence_Main_lvl66"
      produceable="true" group="52" targetZones="Home,Friend"
      targetType="Building" targetDescription="EventMonster_WeeklyChallengeShip"
      instantBuildCosts="6" buffEfficiency="KingdomRecruitTrainee|1">
    <Costs><Cost name="SimplePaper" count="6"/></Costs>
</Buff>
```

Подтверждает С-1 из `oq-resolutions.md`: для типа 1 рецепты берутся из
динамического пула `<Buff produceable="true">`, а пустота
`<TimedProductionList id="1">` — норма.

### Уточнение к С-2 (я был не вполне точен)

В `oq-resolutions.md` С-2 сказано, что `requiresEvent`/`requiresQuest` «на `<Buff>`
не существуют». Это неверно: `requiresQuest` на `<Buff id="581">` ЕСТЬ и содержит
пять квестов. Верная формулировка:

- Атрибуты `requiresQuest` / `requiresEvent` НА `<Buff>` СУЩЕСТВУЮТ — данные есть.
- Атрибуты `requiresUpgradeLevelMin` / `requiresUpgradeLevelMax` на `<Buff>`
  ОТСУТСТВУЮТ — уровневой фильтр для типа 1 невозможен. Ревизия ADR-8 в силе.
- Ветка B клиента `checkRequirements()` НЕ вызывает, то есть сам клиент эти
  квесты не проверяет, но сервер вполне может. Поэтому каталог ДОЛЖЕН
  импортировать `requiresQuest`/`requiresEvent` с `<Buff>` и выставлять
  `availability: "unknown"` по ADR-8. Для данного рецепта это сработало бы
  правильно: заказ прошёл, потому что недельный челлендж был активен.

**Не путать `group` с `productionType`.** У этого бафа `group="52"`, но заказ ушёл
с `productionType=1`. `group` — это вкладка в панели (`buttonBar`), а не тип
производства. Совпадение `group=52` с `productionType=52` (PrintPress) —
случайное и опасное для чтения кода. Новая ловушка **P-25**.

---

## 5. Фактические значения из дампа — база для фикстуры

| Поле | Значение |
| --- | --- |
| `zoneID` / `playerID` | `1601416` (домашняя зона: совпадают) |
| `buildingGrid` | `9001` |
| `productionType` | `1` |
| `type_string` | `EventMonsterBuffDrillManualRough` |
| `amount` / `stacks` | `1` / `1` |
| `index` | `0` (первый в очереди) |
| `uniqueId` | `dUniqueID{uniqueID1: 46853, uniqueID2: 0}` |
| `errorCode` | `0` |
| Стоимость по XML | `SimplePaper × 6`, длительность `420` с |

Дамп ответа сохранить как
`tests/Fixtures/Amf/start_timed_production_91_response.bin` и использовать в тесте
`GameResponseValidator` на успешный заказ.

---

## 6. Чего дамп ВСЁ ЕЩЁ не даёт

Это ответ на команду 91 (один заказ), а НЕ ответ `GET_ZONE` (1001).
Поэтому блокер этапа 2 НЕ снят:

- Неизвестны имена ключей секции очередей в снапшоте зоны
  (`production_queues`, `production_type`, `upgrade_level` в `design.md` §4 —
  всё ещё гипотеза).
- Неизвестно, приходит ли `stackingBuffs_vector` (нужно для F-4).
- Неизвестно, как выглядит очередь из НЕСКОЛЬКИХ заказов и как
  нумеруются `index`.

**Что нужно:** дамп ответа на команду 1001 (`GET_ZONE`) с аккаунта, где в
баф-здании стоит хотя бы два заказа. Желательно в виде сырого бинарника
или base64 — не копированным текстом.

---

## 7. OQ-4 (новый, мелкий)

В ЗАПРОСЕ поля `uniqueId` и `playerId` присутствуют в traits, но их точные
значения по присланному тексту не восстанавливаются: текст прошёл через
копирование и непечатные байты потеряны. Варианты: `null` либо
`dUniqueID{0, 0}` для `uniqueId`; `0` либо реальный id для `playerId`.

Почему важно: от этого зависит байт-в-байт совпадение тела запроса
(`0x01` против вложенного объекта `dUniqueID`) — то есть главный тест из
`verification.md` §2.

**Что нужно:** тот же запрос в base64 (как был прислан ответ), а не текстом.
До тех пор в фикстуре запроса использовать `uniqueId = null`, `playerId = 0`
и пометить тест как требующий подтверждения.

---

## 8. Сводка правок по файлам спека

| Файл | Что меняется |
| --- | --- |
| `decisions.md` ADR-3 | 5 полей → 14 полей (ADR-3-R) |
| `requirements.md` AC-3 | «ровно 5» → «ровно 14 в точном порядке» (AC-3-R) |
| `design.md` §2 | VO: 5 свойств → 14, три из них `float` |
| `design.md` §7 | добавить чтение `body.data.errorCode` и `...data[0].index` |
| `verification.md` §2 | заменить ассерт про 5 полей; добавить ассерт про `0x05` |
| `pitfalls.md` | удалить P-10 («лишние поля»); добавить P-22..P-25 |
| `oq-resolutions.md` С-2 | уточнить: `requiresQuest` на `<Buff>` есть |
| `implementation-plan.md` этап 3 | фикстура ответа уже есть; фикстура запроса — OQ-4 |
