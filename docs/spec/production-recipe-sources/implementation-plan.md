# Implementation Plan

Порядок этапов обязателен. Каждый этап заканчивается зелёными гейтами и отметкой «готово». Не начинай следующий, пока предыдущий не закрыт.

Гейты для каждого этапа одинаковы:

```
vendor/bin/pint --test
vendor/bin/phpstan analyse
php artisan test
npm run build      # только на этапах, где есть фронт
```

---

## Этап 0. Подготовка данных

Не трогает логику. Цель — положить файлы на место и научиться их находить.

- [ ] Распаковать архив в `docs/references/xml/`, сохранив исходный `README.md`.
- [ ] Не удалять старые `docs/references/globals.xml`, `icons.xml`, `collections.xml` (P-52).
- [ ] Добавить в `docs/references/README.md` таблицу соответствия старых и новых файлов (таблица готова в `data-sources.md` §3).
- [ ] Реализовать `GameXmlLocator` — автоопределение четырёх рабочих файлов по корневому тегу и маркерному узлу (FR-27, P-43).
- [ ] Тест: `GameXmlLocatorTest` находит все четыре файла без хардкода имён.
- [ ] Тест: локатор бросает осмысленное исключение, если файл не найден.

**Критерий готовности:** гейты зелёные, `ImportProductionCatalog` всё ещё работает как раньше.

---

## Этап A. Каркас источников

Цель — вынести существующую логику в интерфейс без изменения поведения.

- [ ] Создать `RecipeSourceInterface`.
- [ ] Создать `ExplicitListRecipeSource` — перенос ветки C из `ImportProductionCatalog`.
- [ ] Создать `BuffPoolRecipeSource` — перенос ветки B/D.
- [ ] Создать `UnsupportedRecipeSource`.
- [ ] Создать `config/game_production_sources.php` с картой из `design.md` §4.
- [ ] Создать `DurationNormalizer`, `InstantFinishCostNormalizer`, `CostNormalizer`.
- [ ] Перевести `ImportProductionCatalog` на вызов источников через карту.

**Критерий готовности:** `config/game_production.php` после регенерации содержит **те же типы и то же число рецептов**, что и до этапа. Это чистый рефакторинг. Сравни диффом.

---

## Этап B. Военные юниты — типы 0 и 8

- [ ] `MilitaryUnitRecipeSource` с обязательной опцией `elite`.
- [ ] Фильтр `produceable="true"`, разделение строго по атрибуту `isElite` (P-38).
- [ ] `type_string` берётся из атрибута `type` (P-37).
- [ ] Длительность — `productionTimeSeconds` через нормализатор.
- [ ] Пометка `is_population` для строки `Population` (P-49).
- [ ] Добавить поле `population` в результат `BuffProductionCostCalculator`.

**Критерий готовности:** в конфиге тип 0 содержит ровно 9 рецептов, тип 8 — ровно 7. Казармы в UI показывают список.

---

## Этап C. Коллекции — тип 4

- [ ] `CollectionRecipeSource`, чтение только через XML-парсер (P-31).
- [ ] Стоимость из `<resource name amount>` (P-36).
- [ ] `instantFinishCost` из `InstantBuildCosts` с заглавной I (P-35).
- [ ] `requires_player_level_min` из `pLvl` или `minLevel`.
- [ ] `requires_event` из `requiresEvent`.
- [ ] `output_buff_name` из `outBuffName`.
- [ ] Поставить `unverified_protocol = true` всем коллекциям (OQ-4).
- [ ] Добавить причину отказа `recipe_requires_inactive_event` в политику (шаг 9).

**Критерий готовности:** ровно 16 рецептов в типе 4, среди них НЕТ `CountrySaying`.

---

## Этап D. Группы бафов — типы 6 и 11

Самый маленький этап.

- [ ] `BuffGroupRecipeSource` с обязательной опцией `group`.
- [ ] Карта `6 => 5`, `11 => 11` только из конфига (P-39).
- [ ] Тест, падающий при отсутствии опции `group`.

**Критерий готовности:** тип 6 — 6 рецептов, тип 11 — 6 рецептов.

---

## Этап E. Очки навыков — тип 2 (Переплётчик)

Самый сложный этап, потому что тут прогрессивная цена.

- [ ] `SkillPointRecipeSource`, `type_string` из атрибута `id`.
- [ ] Заполнить `cost_tiers` всеми `<productionLevel>` с порогами (P-45).
- [ ] `costs` = копия первого тира, `cost_is_lower_bound = true`.
- [ ] `duration_seconds` = длительность первого тира.
- [ ] `max_amount_per_order = 1`, `max_stacks_per_order = 1` (доказано `SkillProductionPanel`).
- [ ] UI: скрыть слайдеры при лимите 1, показать «≥» при `is_lower_bound`.
- [ ] Новые причины отказа `amount_exceeds_recipe_limit`, `stacks_exceeds_recipe_limit`.

**Критерий готовности:** тип 2 содержит ровно 3 рецепта: `Manuscript`, `Tome`, `Codex`. В UI Переплётчика — три позиции с ценой «от».

---

## Этап F. Гейт покрытия и UI-состояния

Этот этап делает повторение исходного бага невозможным.

- [ ] `CatalogCoverageTest`: для каждого из 69 типов из ICONS — либо есть рецепты, либо явный `unsupported:*` (INV-6).
- [ ] Три различимых состояния в `ProducerPicker.vue` (`design.md` §10).
- [ ] Проброс причины отказа политики в UI (FR-20, P-50).
- [ ] Переводы всех новых ключей в `lang/{en,ru,uk}/tasks.php`.
- [ ] Регенерация `resources/js/lang/generated/*.json`.
- [ ] Тест паритета локалей: во всех трёх языках одинаковый набор ключей.
- [ ] Обновить `walkthrough.md`.

**Критерий готовности:** все гейты зелёные, все AC из `requirements.md` проверены.

---

## Ожидаемый итог по числам

После этапа F в `config/game_production.php` должно быть:

| Тип | Было | Стало |
| --- | --- | --- |
| 0 | отсутствует | 9 |
| 1 | 299 | 299 |
| 2 | отсутствует | 3 |
| 4 | отсутствует | 16 |
| 5 | 269 | 269 |
| 6 | отсутствует | 6 |
| 7 | отсутствует | 0 + метка `unsupported:needs_tier_data` |
| 8 | отсутствует | 7 |
| 11 | отсутствует | 6 |
| 12–72 | без изменений | без изменений |

Если твои числа расходятся с этой таблицей — у тебя ошибка, а не у спека. Сначала сверься с `fixtures/summary.json`.
