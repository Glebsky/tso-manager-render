# Тип 7 — Combat 3 Units (здание Barracks3)

В v1.0 этот тип был отложен. Сейчас он полностью разблокирован. Делаем.

---

## 1. Источник данных

**Файл:** `docs/references/xml/settings/game_units.xml` — 14 189 байт, корневой тег `<GameUnit>`.

**Псевдоним в спеке:** `UNITS`. Добавь его пятым рабочим файлом рядом с GLOBALS, ICONS, SKILLPOINTS, COLLECTIONS.

**Маркер автоопределения:** корневой тег `GameUnit` ПЛЮС наличие узла `<UnitData>`. Имя файла в код не зашивать (P-43 действует и здесь).

### Почему в v1.0 его пропустили

Поиск вёлся по тегу `<MilitaryUnit>` — которого здесь нет. Здесь тег `<Unit>`. Это **другая сущность с другой схемой**, а не копия военных юнитов из GLOBALS.

Соответствие классам клиента:

| Класс в `client_scripts.txt` | Файл | Тег | Обслуживает типы |
| --- | --- | --- | --- |
| `cMilitaryUnitDescription` | GLOBALS | `<MilitaryUnit>` | 0 и 8 |
| `cMilitaryUnitData` | **UNITS** | `<Unit>` | **7** |

Именно поэтому в клиенте два разных метода: `GetAllUnitDescriptions(true)` для типов 0/8 и `GetAllUnitDataByTier(true, tier)` для типа 7. Загадка из v1.0 решена.

---

## 2. Формат узла — читай внимательно

```xml
<GameUnit>
  <UnitData>
    <Unit Type="ExpeditionCuirassier" ArmorType="Heavy" HP="180" BaseDamage="30"
          XP="1" PvPXP="1" Tier="3" CombatBatchSize="20" group="AttackUnits">
      <Costs>
        <Cost Type="Population" Amount="1" />
        <Cost Type="Horse"      Amount="5" />
        <Cost Type="Saber"      Amount="6" />
      </Costs>
      <Abilities>
        <Ability Type="DamageBonusPercentMediumArmor" Value="35" />
      </Abilities>
      <Properties>
        <Property Type="IsProducible"     Value="1" />
        <Property Type="ProductionTime"   Value="360" />
        <Property Type="InstantBuildCost" Value="10" />
        <Property Type="Id"               Value="101" />
        <Property Type="UIPriority"       Value="3" />
        <Property Type="UnitCategory"     Value="2" />
        <Property Type="AttackPriority"   Value="70" />
      </Properties>
    </Unit>
  </UnitData>
</GameUnit>
```

### Критические отличия от всех остальных источников

Этот файл ломает все привычки, набранные на четырёх остальных:

| Что | Везде ещё | Здесь |
| --- | --- | --- |
| Имя рецепта | `name` или `type` (строчные) | **`Type`** с заглавной |
| Ресурс в стоимости | `name` | **`Type`** |
| Количество в стоимости | `count` или `amount` | **`Amount`** с заглавной |
| Длительность | атрибут узла | **вложенный `<Property Type="ProductionTime">`** |
| Мгновенное завершение | атрибут узла | **`<Property Type="InstantBuildCost">`** без `s` на конце |
| Признак производимости | `produceable="true"` | **`<Property Type="IsProducible" Value="1">`** |

То есть четвёртое написание стоимости и четвёртое написание стоимости мгновенного завершения. Нормализаторы из v1.0 обязаны быть расширены, а не скопированы.

Заметь также: `<Abilities>` бывает пустым, а `group` есть не у всех узлов (13 из 29). Не падай на отсутствии.

---

## 3. Фильтрация

Всего в файле **29 узлов `<Unit>`**. Большая часть — это враги и боссы, а не то, что игрок может производить.

Правило отбора — ровно одно:

```
берём узел, если <Property Type="IsProducible" Value="1" />
```

Результат: **7 узлов из 29**. Если у тебя получилось другое число — ошибка у тебя.

**НЕ фильтруй** по подстроке `Expedition` в имени. Совпадение случайное, это тот же класс ошибки, что и `EliteSoldier` из P-38.

---

## 4. Полный ожидаемый результат — эталон для самопроверки

Все семь — `Tier = 3`, `ProductionTime = 360` секунд, `Population = 1`.

| Id | Type | group | InstantBuildCost | Стоимость |
| --- | --- | --- | --- | --- |
| 101 | `ExpeditionCuirassier` | AttackUnits | 10 | Population 1, Horse 5, Saber 6 |
| 102 | `ExpeditionArcher` | AttackUnits | 10 | Population 1, Beer 10, CompositeBow 6 |
| 103 | `ExpeditionPikeman` | AttackUnits | **2** | Population 1, Beer 10, Pike 6 |
| 104 | `ExpeditionSwordsman` | TankUnits | 10 | Population 1, Beer 10, SpikedMace 3 |
| 105 | `ExpeditionCrossbowman` | TankUnits | 10 | Population 1, Beer 10, ExpeditionCrossbow 3 |
| 106 | `ExpeditionKnight` | TankUnits | 10 | Population 1, Horse 5, BattleLance 3 |
| 107 | `ExpeditionTank` | **EliteUnits** | 10 | Population 1, **ValorPoint 4** |

Два момента, которые выглядят как опечатки, но таковыми не являются: у `ExpeditionPikeman` стоимость мгновенного завершения 2, а не 10; `ExpeditionTank` стоит `ValorPoint`, а не обычный товар. Не «исправляй» их.

Готовая фикстура: `fixtures/combat3_units.json`.

---

## 5. Как работать с тиром

В v1.0 тир был главным страхом: казалось, что набор рецептов зависит от состояния UI и его нельзя уложить в статичный каталог.

Фактически: **все семь производимых юнитов имеют один и тот же Tier 3.** Выбора тира в данных не существует — переключатель в клиенте есть, но выбирать не из чего.

Поэтому правило такое:

- Импортёр берёт **все** производимые юниты независимо от тира.
- Значение тира сохраняется в рецепте как поле `tier` — на будущее и для группировки в UI.
- Группа (`AttackUnits` / `TankUnits` / `EliteUnits`) сохраняется как `unit_group` и используется для заголовков секций в списке — именно так группирует клиент через `GetGroup()`.
- Поле `unit_group` НЕ участвует в отправке команды 91. Это чисто презентационное поле.

Если в будущем патче игры появится второй тир с производимыми юнитами — каталог подхватит их автоматически, а тест числа рецептов упадёт и подскажет, что нужно пересмотреть UI. Это желаемое поведение.

---

## 6. Открытый риск: что именно летит в `type_string`

Строго говоря, у нас нет трафика заказа из Barracks3. Два кандидата:

1. атрибут `Type`, например `ExpeditionCuirassier`;
2. числовой `<Property Type="Id">`, например `101`.

**Решение:** берём `Type` — во всех остальных шести источниках `type_string` всегда строковое имя, никогда числовой id. Это обоснованный вывод по аналогии, а не доказательство.

Поэтому все 7 рецептов помечаются `unverified_protocol = true` — точно так же, как коллекции в v1.0. Первый же успешный заказ в игре снимает флаг. Ручная проверка в Barracks3 становится обязательным пунктом приёмки.
