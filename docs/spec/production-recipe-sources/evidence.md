# Evidence — доказательная база

В этом документе собраны первоисточники для каждого утверждения в `recipe-source-matrix.md`. Если ты собираешься отклониться от спека — сначала перепроверь соответствующий пункт здесь.

Обозначения источников:

- **[T]** — живой трафик AMF (высший ранг истины)
- **[C]** — `docs/references/client_scripts.txt` (декомпилированный клиент)
- **[X]** — игровой XML
- **[F]** — фикстура, сгенерированная из [X]

---

## 1. Девять пустых списков — это замысел, а не повреждённые данные

**[X]** `GLOBALS`, узлы `<TimedProductionList>`. Дословное содержимое:

```xml
<TimedProductionList id="0">
  <!-- Military Units: defined by the produceable MilitaryUnits -->
</TimedProductionList>
<TimedProductionList id="1">
  <!-- Buffs: defined by the produceable Buffs (produceable="true") -->
</TimedProductionList>
<TimedProductionList id="2">
  <!-- Skillpoints: defined by the skill points in the skills.xml -->
</TimedProductionList>
<TimedProductionList id="3">
  <!-- Effects -->
</TimedProductionList>
<TimedProductionList id="4">
  <!-- Collectibles -->
</TimedProductionList>
<TimedProductionList id="6">
  <!-- Combat 3 Weapons: defined by the produceable Buffs (produceable="true") in group 5 (group="5") -->
</TimedProductionList>
<TimedProductionList id="7">
  <!-- Combat 3 Units -->
</TimedProductionList>
<TimedProductionList id="8">
  <!-- Elite Units -->
</TimedProductionList>
<TimedProductionList id="11">
  <!-- Armory 2 -->
</TimedProductionList>
```

Вывод: пустой список — валидное состояние. Импортёр НЕ должен падать или варнить на пустой список из этих девяти. Он должен переключиться на альтернативный источник.

Соответствующий тест: «importer не пишет warning для списков 0,1,2,3,4,6,7,8,11».

---

## 2. Фабрика заказов клиента — ветвление по productionType

**[C]** `client_scripts.txt`, строки 484182–484215.

Логика (пересказ без потери смысла):

| `productionType` | Класс заказа |
| --- | --- |
| 1 (BUFF) | `cBuffProductionOrder(…, false)` |
| 0 (MILITARY_UNIT) | `cMilitaryUnitProductionOrder` |
| 8 (ELITE_UNITS) | `cMilitaryUnitProductionOrder` |
| 2 (SKILL) | `SkillTimedProductionOrder` |
| 4 (COLLECTIONS) | `CollectionsTimedProductionOrder` |
| 6 (COMBAT_THREE_WEAPONS) | `cBuffProductionOrder(…, true)` |
| 11 (COMBAT_THREE_WEAPONS_2) | `cBuffProductionOrder(…, true)` |
| 7 (COMBAT_THREE_UNITS) | `cMilitaryUnitProductionOrder` |
| остальные | `EffectTimedProductionOrder` или `cBuffProductionOrder` |

**Зачем это важно:** разные классы заказа подтверждают, что источники рецептов тоже разные. Однако **все они в итоге шлют одну и ту же команду 91 с одним и тем же `dTimedProductionVO`**. Различается только то, откуда взяты `type_string` и цена.

Следствие для архитектуры: нам НЕ нужны разные хэндлеры или разные типы таски. Нужен один `ProduceBuffHandler` и разные источники каталога. Это ключевое архитектурное решение всей задачи.

---

## 3. Разделение юнитов 0 / 8

**[C]** `client_scripts.txt`, строки 307536–307550.

```actionscript
_local_11 = cMilitaryUnitDescription.GetAllUnitDescriptions(true);
for each (_local_12 in _local_11) {
    if (((_local_12.GetIsElite()) && (this.mProductionType == ELITE_UNITS)) ||
        ((!(_local_12.GetIsElite())) && (this.mProductionType == MILITARY_UNIT))) {
        _local_10.push(_local_12);
    }
}
this.buttonBar.dataProvider = null;
```

**[C]** строки 134260 и 145559 — обратное соответствие:

```actionscript
productionType = (isElite) ? ELITE_UNITS : MILITARY_UNIT;
```

**[F]** `fixtures/military_units.json` — проверено: атрибут `playerLevel` отсутствует у всех 16 юнитов (значение `null`).

Вывод: разделение строго бинарное по `isElite`. Любая дополнительная фильтрация — выдумка.

**История ошибки:** на раннем этапе была гипотеза «фильтр по `isElite` + `playerLevel`». Она оказалась неверной. Не повторяй.

---

## 4. Панель Переплётчика — отдельный UI, amount жёстко 1

**[C]** `client_scripts.txt`, строки ~302700–303200, класс `SkillProductionPanel`.

Факты:

1. Три кнопки `orderBtn0`, `orderBtn1`, `orderBtn2` — жёстко три, по числу элементов `global.skillPoints_vector[0..2]` (строки 302832–302834, 303140–303166).
2. Метод `Order()` ставит `amount = 1` константой и не трогает `stacks`.
3. Тултип берётся по ключу `Skillpoint_<id>` из `LOCA_GROUP.LABELS`.

**[C]** строка 475386: `for each (… in global.skillPoints_vector)` — вектор заполняется из XML очков навыков.

---

## 5. Доступность рецепта приходит в снапшоте зоны

**[C]** `client_scripts.txt`, строки 484172–484180:

```actionscript
if (!gi.mRequirements.timedProductionRequirements_vector[_arg_1.type_string]
        .isFulfilledForSkillList(gi.mCurrentPlayer.getSkills())) {
    // заказ не создаётся
}
```

**[T]** В снапшоте зоны (ответ 1002) есть поле:

```
$.body.data.data.requirements.timedProductionRequirements_vector
```

Тип — `dRequirementListsVO`. Ключ — именно `type_string` рецепта.

### Следствие — два слоя фильтрации

| Слой | Где | Что делает |
| --- | --- | --- |
| Статический | импортёр → конфиг | Что вообще существует в игре для этого типа здания |
| Динамический | рантайм → снапшот зоны | Что доступно КОНКРЕТНОМУ игроку сейчас |

Не пытайся заменить второй слой первым. Каталог общий для всех аккаунтов, требования — персональные.

---

## 6. `collectedTime` — это длительность в миллисекундах

Это закрывает старый вопрос OQ-5 из пакета `buff-production-queue`.

**[T]** Заказ Переплётчика из реального снапшота:

```
uniqueId      = {46557, 0}
playerId      = 1601416
productionType= 2
type_string   = "Tome"
amount        = 1
producedItems = 1
collectedTime = 259200000.0
stacks        = 1
index         = 1
buildingGrid  = 0
```

**[X]** `SKILLPOINTS`, `<skillPoint id="Tome">` → `productionTime="259200"`.

Арифметика: `259200 с × 1000 = 259 200 000 мс`. Совпадение бит в бит.

### Почему это не может быть меткой времени

Unix-время в миллисекундах сейчас — порядка 1.7×10¹². Значение 2.6×10⁸ меньше на четыре порядка — это физически не может быть датой.

**Дополнительное подтверждение [T]:** в ответе на команду 91 для только созданного заказа (`EventMonsterBuffDrillManualRough`) было `collectedTime = 0.0`. Если бы это была метка времени создания, там было бы текущее время, а не ноль.

### Гипотеза семантики (НЕ факт)

`collectedTime` похоже на «оставшееся/накопленное время текущего элемента в мс». Для нашей задачи это неважно: мы **читаем** это поле, но никогда не отправляем осмысленное значение — при создании заказа туда идёт `0.0`.

Не строй оценку времени завершения очереди на `collectedTime`. Строй её на `duration_seconds` из каталога — это доказанно корректный путь.

---

## 7. Три разных имени для одного и того же понятия

**[X]** Фактические имена атрибутов длительности:

| Узел | Атрибут |
| --- | --- |
| `<Buff>` | `productionTime` |
| `<TimedProduction>` | `duration` |
| `<MilitaryUnit>` | `productionTimeSeconds` |
| `<productionLevel>` | `productionTime` |
| `<collection>` | `productionTime` |

Имена атрибутов стоимости мгновенного завершения:

| Узел | Атрибут |
| --- | --- |
| `<Buff>` | `instantBuildCosts` |
| `<MilitaryUnit>` | `instantBuildCosts` |
| `<TimedProduction>` | `instantFinishCost` |
| `<skillPoint>` | `instantFinishCost` |
| `<collection>` | `InstantBuildCosts` ← заглавная I |

Теги стоимости:

| Узел | Дочерний тег | Атрибут количества |
| --- | --- | --- |
| `<Buff>`, `<MilitaryUnit>`, `<TimedProduction>` | `<Costs><Cost>` | `count` |
| `<skillPoint>` | `<productionLevel><cost>` | `count` |
| `<collection>` | `<resource>` | `amount` |

Это не опечатки в спеке. Это реальный бардак в игровых данных, накопившийся за 15 лет. Каждый парсер должен знать свои имена. Единого универсального парсера быть не может.

---

## 8. Здание = тип производства, а не очередь

**[T]** В снапшоте у заказа Переплётчика `buildingGrid = 0`, хотя сам Переплётчик стоит на гриде 8998.

**[T]** В ответе на команду 91 для Мастерской улучшений `buildingGrid = 9001` — то есть при создании грид передаётся, но в снапшоте может быть обнулён.

**Важный вывод:** очередь привязана к `productionType`, а не к конкретному зданию. Если у игрока два здания одного типа — у них общая очередь. Поле `meta.shares_queue_with` в пейлоаде задачи существует именно поэтому. Не удаляй его.

---

## 9. Лимиты количества

**[C]** `client_scripts.txt`, строки 523620–523621:

```actionscript
MAX_PRODUCTION_AMOUNT = 25;
MAX_PRODUCTION_STACKS = 200;
```

Подтверждено пользователем по опыту игры: «стак обычно максимум 25 предметов, и 200 таких стаков».

Переопределения по типам:

| Тип | max amount | max stacks | Причина |
| --- | --- | --- | --- |
| 2 (skillpoints) | **1** | **1** | жёстко в `SkillProductionPanel.Order()` |
| все остальные | 25 | 200 | глобальные константы |

Отдельно: в UI v1 слайдер `stacks` заблокирован значением 1 для всех типов (старый пункт F-4: мы не знаем, какие рецепты стакаются). Граница 200 зафиксирована в коде как константа на будущее.

---

## 10. Статистика источников (для самопроверки)

**[F]** `fixtures/summary.json`:

```
lists_total          71
lists_empty          [0, 1, 2, 3, 4, 6, 7, 8, 11]   ← ровно 9
producers_count      69
buff_pool_count      299
buff_pool_with_costs 248
buff_group_5         6
buff_group_11        6
military_units_count 16   (9 regular + 7 elite)
collections_count    16
skillpoints          [Manuscript, Tome, Codex]
```

Если твой парсер даёт другие числа — ошибка в парсере, а не в данных. Самые частые причины:

- получил 17 коллекций → ты читаешь XML регуляркой и захватил закомментированный пример;
- получил 0 в непустых списках → ты ищешь закрывающий тег жадной регуляркой;
- получил 379 юнитов ␲ 16 → забыл фильтр `produceable="true"`.
