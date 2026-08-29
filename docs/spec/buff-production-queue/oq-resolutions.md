# Закрытие открытых вопросов (OQ-1..OQ-3)

Дата: 2026-08-28. Все три вопроса ЗАКРЫТЫ. Этот файл имеет ПРИОРИТЕТ над
соответствующими разделами `data-sources.md` §6.3, `decisions.md` (OQ-1..3, ADR-9)
и `implementation-plan.md` этап 0/5, если тексты расходятся.

---

## OQ-1 — Условие ветвления каталога рецептов: ЗАКРЫТ

### Старая гипотеза НЕВЕРНА

В `data-sources.md` §6.3 было предположено:
`!TIMED_PRODUCTION_TYPE.isHardcoded(this.mProductionType)`.
**Это неверно.** `isHardcoded` в этой функции не участвует вообще.

### Фактическая логика

Источник: `client_scripts.txt:307850–307917`, функция
`SetFilteredReciepeList(_arg_1:ItemClickEvent = null)` контроллера
`TimedProductionInfoPanel`.
Ветвление идёт ПО ЗНАЧЕНИЮ `mProductionType`, четыре ветви в таком порядке:

```actionscript
var _local_3:String = (this.mPanel.buttonBar.dataProvider as ArrayCollection)
    .getItemAt(this.mSelectedTabIndex).group;   // выбранная вкладка = group

// ВЕТКА A
if (this.mProductionType == TIMED_PRODUCTION_TYPE.COMBAT_THREE_UNITS)   // == 7
{
    for each (_local_4 in cMilitaryUnitData.GetAllUnitDataByTier(true, this.mCurrentSelectedUnitTier))
    { if (_local_4.GetGroup() == _local_3) { _local_2.push([...]); }; }
}
else
{
    // ВЕТКА B  <-- НАШ СЛУЧАЙ ДЛЯ БАФОВ
    if (this.mProductionType == TIMED_PRODUCTION_TYPE.BUFF)             // == 1
    {
        for each (_local_5 in cBuff.GetProduceableBuffDefinitions(this.mGI))
        {
            if (_local_5.GetGroup_string() == _local_3.toString())
            {
                if (BuffAdventureController.isActiveBuffAdventureBuffOrNormalBuff(_local_5.GetName_string()))
                { _local_2.push([_local_5, this.GetAmountInProduction(_local_5), this.mBuilding]); };
            };
        };
    }
    else
    {
        // ВЕТКА C — явный список из globals.xml
        if (((this.mProductionType == TIMED_PRODUCTION_TYPE.RARE_BUFFS)          // == 5
            || (TIMED_PRODUCTION_TYPE.isSimpleProduction(this.mProductionType))
            || (TIMED_PRODUCTION_TYPE.isTimedProduction(this.mProductionType))))
        {
            for each (_local_6 in gMisc.iterableToArray(global.timedProductions_vector[this.mProductionType]))
            {
                if (((((_local_6.GetGroup().toString() == _local_3)
                    && (RequirementsHelper.checkRequirements(this.mGI, _local_6.requiresEvent, _local_6.requiresQuest)))
                    && (this.mBuilding.GetUpgradeLevel() >= _local_6.requiresUpgradeLevelMin))
                    && (this.mBuilding.GetUpgradeLevel() <= _local_6.requiresUpgradeLevelMax)))
                {
                    if (BuffAdventureController.isActiveBuffAdventureBuffOrNormalBuff(_local_6.GetProductionName_string()))
                    { _local_2.push([...]); };
                };
            };
        }
        else
        {
            // ВЕТКА D — динамический пул без adventure-фильтра
            for each (_local_7 in cBuff.GetProduceableBuffDefinitions(this.mGI))
            { if (_local_7.GetGroup_string() == _local_3) { _local_2.push([...]); }; }
        };
    };
};
this.mPanel.availableOrdersList.dataProvider = _local_2;
```

### Сводная таблица ветвей

| Ветка | Условие | Источник рецептов | Фильтр уровня/событий | Adventure-фильтр |
| --- | --- | --- | --- | --- |
| A | `type == 7` | `cMilitaryUnitData.GetAllUnitDataByTier` | нет | нет |
| **B** | **`type == 1` (BUFF)** | **`cBuff.GetProduceableBuffDefinitions()`** | **НЕТ** | **ЕСТЬ** |
| C | `type == 5` ‖ `isSimpleProduction` ‖ `isTimedProduction` | `global.timedProductions_vector[type]` | ЕСТЬ | ЕСТЬ |
| D | всё остальное (фактически — `culturebuilding`) | `cBuff.GetProduceableBuffDefinitions()` | нет | нет |

### Главные следствия для реализации

**С-1.** Для `productionType == 1` (`ProvisionHouse` — «универсальная мастерская»)
рецепты берутся ИСКЛЮЧИТЕЛЬНО из динамического пула
`<Buff produceable="true">` (299 записей в `globals.xml`). Пустота
`<TimedProductionList id="1">` — НОРМА, а не дефект данных. Гипотеза из предыдущего
ответа подтверждена.

**С-2. КРИТИЧНО для ADR-8 и AC-8.** В ветке B НЕТ ни уровневого фильтра
(`requiresUpgradeLevelMin/Max`), ни `checkRequirements(requiresEvent, requiresQuest)`.
Эти атрибуты живут на `<TimedProduction>`, а не на `<Buff>`, и для бафов просто
не существуют.
**Ревизия ADR-8:** уровневой фильтр применять только к ветке C. Для ветки B
(бафы) единственный фильтр — совпадение `group` с выбранной вкладкой плюс
`produceable="true"`. Соответственно `ProductionOrderPolicy` НЕ должна возвращать
`RecipeLevelLocked` для типа 1 — там нет данных для такого решения. Причину
оставить в enum для типов ветки C.

**С-3.** `BuffAdventureController.isActiveBuffAdventureBuffOrNormalBuff()` — реальный
фильтр в ветке B: часть бафов доступна только при активном buff-приключении.
Состояние приключения в снапшоте зоны отсутствует, поэтому воспроизвести его
на бэкенде невозможно. Решение: такие рецепты показывать с
`availability: "unknown"` (тот же механизм, что в ADR-8 для
`requiresEvent`/`requiresQuest`), отказ при исполнении обрабатывать по ADR-7.
Признак adventure-бафа в каталоге: имя содержит `PropagationBuff_Adventure`
(см. списки 19, 21, 23 в `data-sources.md`) — требует проверки при реализации.

**С-4. Откуда берётся `isSimpleProduction`/`isTimedProduction`/`isCultureBuilding`.**
НЕ из `icons.xml` и НЕ из атрибута `ui`. Источник — атрибут `type` на
`<TimedProductionList>` в `globals.xml`:

```actionscript
// client_scripts.txt:527586-527599, parseTimedProductions(_arg_1:cXML)
_local_3 = _local_2.GetAttributeInt("id");
_local_4 = _local_2.GetAttributeString_string("type", TIMED_PRODUCTION_TYPE.DEFAULT_TYPE_string);
TIMED_PRODUCTION_TYPE.add(_local_3, _local_4);
```

`DEFAULT_TYPE_string` = `"hardcoded"`. Распознаваемые значения (`typeStrToInt`,
`73814–073828`): `culturebuilding` → 20000, `simpleproduction` → 30000,
`timedproduction` → 40000, `hardcoded` → 10000, иначе → `-1`, а `add()` подменяет
`-1` на `TYPE_HARDCODED`.

Фактическое содержимое `globals.xml` (70 списков):

| id | атрибут `type` |
| --- | --- |
| 0, 1, 2, 3, 4, 5, 6, 7, 8, 11 | ОТСУТСТВУЕТ → `hardcoded` |
| 12, 13, 17, 19–23, 27, 28, 37, 42, 43, 46, 48, 49, 51–53, 56, 62, 63, 67 | `culturebuilding` |
| 14–16, 18, 24, 26, 30, 31, 47 | `simpleproduction` |
| 25, 29, 32–36, 38–41, 44, 45, 50, 54, 55, 57–61, 64–66, 68–71 | `timedproduction` |

**Ловушка (важно).** Обе функции `isHardcoded` и `isTimedProduction`
возвращают `true` при ОТСУТСТВИИ записи в `mapIdToType`:

```actionscript
public static function isHardcoded(_arg_1:int):Boolean {
    return ((mapIdToType.getItem(_arg_1) == null) || (mapIdToType.getItem(_arg_1) == TYPE_HARDCODED));
}
public static function isTimedProduction(_arg_1:int):Boolean {
    return ((mapIdToType.getItem(_arg_1) == null) || (mapIdToType.getItem(_arg_1) == TYPE_TIMEDPRODUCTION));
}
// а вот эти две — строгие, null даёт false:
public static function isSimpleProduction(_arg_1:int):Boolean {
    return ((!(mapIdToType.getItem(_arg_1) == null)) && (mapIdToType.getItem(_arg_1) == TYPE_SIMPLEPRODUCTION));
}
public static function isCultureBuilding(_arg_1:int):Boolean {
    return ((!(mapIdToType.getItem(_arg_1) == null)) && (mapIdToType.getItem(_arg_1) == TYPE_CULTUREBUILDING));
}
```

Следствие: типы 0, 2, 3, 4, 6, 7, 8, 11 формально удовлетворяют
`isTimedProduction` и попадают в ветку C с ПУСТЫМ списком. Для типа 1 это
не страшно — его перехватывает ветка B выше. Типы 0, 6, 7, 8, 11 — военные,
вне области задачи. Типы 2 (SKILL), 3 (EFFECT), 4 (COLLECTIONS) — тоже вне
области (F-6). Вывод для кода: **не полагаться на `isTimedProduction`
как на признак «есть явный список»** — проверять непосредственно наличие
записей в каталоге.

**С-5. Правка к `design.md` §3** (`ProductionCatalogInterface`). Добавить в структуру
`config/game_production.php` ключ `recipe_source` со значениями `"buff_pool"` (ветка B/D)
или `"explicit_list"` (ветка C), плюс `list_type` (`hardcoded` / `culturebuilding` /
`simpleproduction` / `timedproduction`) для каждого `productionType`. Команда импорта
вычисляет это один раз по той же логике, что выше — рантайм её не повторяет.

---

## OQ-2 — Максимальные значения `amount` и `stacks`: ЗАКРЫТ

Найдены точные константы. Слова оператора «стак обычно максимум 25 предметов,
и 200 таких стаков» подтверждены кодом буквально:

```actionscript
// client_scripts.txt:523620-523621, класс defines
public static const MAX_PRODUCTION_AMOUNT:int = 25;
public static const MAX_PRODUCTION_STACKS:int = 200;
```

Настройка слайдеров (`client_scripts.txt:307386–307393`):

```actionscript
this.mPanel.amountSlider.value   = 1;
this.mPanel.amountSlider.minimum = 1;
this.mPanel.amountSlider.maximum = defines.MAX_PRODUCTION_AMOUNT;   // 25
this.mPanel.stackSlider.value    = 1;
this.mPanel.stackSlider.minimum  = 1;
this.mPanel.stackSlider.maximum  = defines.MAX_PRODUCTION_STACKS;   // 200
```

Клиентская валидация при создании продакшна (`client_scripts.txt:484177–484179`,
класс `cTimedProductionQueue`):

```actionscript
if (_arg_1.amount > defines.MAX_PRODUCTION_AMOUNT)
{
    cLog.error("Cant create Production: Production Amount higher then allowed. ... MAX_AMOUNT=" + defines.MAX_PRODUCTION_AMOUNT);
}
```

### Ревизии решений

| Было | Стало | Основание |
| --- | --- | --- |
| `produceBuffRules`: `amount` min:1 **max:99** | `amount` min:1 **max:25** | 523620 |
| ADR-9: `stacks` всегда 1, валидация `in:1` | `stacks` min:1 **max:200**, дефолт 1 | 523621, 307393 |
| `ScheduledTaskRequestProduceBuffTest`: `stacks = 2` → 422 | `stacks = 2` → **200/201**; `stacks = 201` → 422; `amount = 26` → 422 | там же |

**Важное уточнение к ADR-9.** Ранее `stacks` был зафиксирован на 1, потому что
`hasStackingBonus` выводится из `GetGOContainer().stackingBuffs_vector.length > 0`
(`78284`) и в снапшоте отсутствует. Это ограничение НЕ исчезло: сервер
может отклонить `stacks > 1` для здания без stacking-бонуса. Поэтому:

- Протокольный слой и валидатор ДОЛЖНЫ допускать 1..200.
- UI первой итерации ПУСКАЕТ только 1 (без слайдера стаков), иначе мы
  покажем оператору вариант, который может молча отвалиться. Слайдер
  стаков открывается в F-4 после того, как `stackingBuffs_vector` появится в
  снапшоте.
- Стоимость уже умножается на `stacks` (§6 `design.md`) — правок не требует.

**Длина очереди остаётся неизвестной.** Константы 25/200 — это лимиты ОДНОГО
заказа, а не числа заказов в `mTimedProductions_vector`. Оператор подтверждает, что
с лимитом длины не сталкивался и «повторять можно много раз». Решение
из ADR-7/OQ-2 сохраняется без изменений: лимит не выдумываем, политика
не выставляет `QueueFull` превентивно, причина остаётся в enum только для
трансляции ошибки сервера. Ручная проверка №6 остаётся в силе.

Дополнительно: в UI игры есть подписи вида `"/ " + amountSlider.maximum` и
`"/ " + stackSlider.maximum` (`265932`, `265938`, `266289`, `266340`) — то есть игра
показывает знаменатель для количества и стаков, но НЕ для длины очереди.
Косвенно подтверждает, что жёсткого клиентского лимита длины нет.

---

## OQ-3 — Конфликт конституций: ЗАКРЫТ

Решение оператора: **PHP 8.5**. Значит `docs/spec/constitution.md` §3 —
авторитетный источник, `docs/spec/laravel-constitution.md` устарел.

Действующие правила для всего кода этой фичи:

| Параметр | Значение |
| --- | --- |
| PHP | ^8.5 |
| Laravel | ^12.0 |
| Форматтер | `./vendor/bin/pint --test` (НЕ `--dirty`) |
| Тесты | Pest |
| Касты модели | метод `casts()`, не `protected $casts = []` |

**Follow-up F-8 (новый).** `docs/spec/laravel-constitution.md` либо удалить, либо
снабдить шапкой «УСТАРЕЛО, см. constitution.md». Пока файл лежит без пометки,
каждый следующий агент будет спотыкаться о тот же конфликт. Вне области
этой фичи, но сделать стоит.

---

## Итог: что поменялось в этапах плана

**Этап 0 (блокеры) — закрыт на 2/3.** Остался только один пункт: снять AMF-фикстуру
зоны с непустой очередью производства — без неё невозможен этап 2 (ключи
`production_queues`, §4 `design.md`, всё ещё гипотетичны).

**Этап 1 (каталог)** — добавить вывод `recipe_source` и `list_type` (С-5).
Критерий «≥299 рецептов» остаётся, но теперь явно: 299 из пула бафов для
типов ветки B/D ПЛЮС записи явных списков для типов ветки C.

**Этап 4 (политика)** — `RecipeLevelLocked` применять только к типам ветки C (С-2).
Добавить тест: для типа 1 уровневая проверка не срабатывает никогда.

**Этап 5 (валидация)** — `amount` max:25, `stacks` max:200 (OQ-2).

**Этап 9 (верификация)** — в `verification.md` §4.7 заменить три строки таблицы
согласно ревизиям OQ-2.
