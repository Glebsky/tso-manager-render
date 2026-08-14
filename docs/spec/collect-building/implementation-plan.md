# Implementation Plan: collect_building (revision 2)

Порядок этапов выбран так, чтобы каждый этап оставлял проект в зелёном состоянии,
а опасная команда `65` подключалась последней и только когда её шлюз уже покрыт тестами.

## Этап 0. База сравнения

Прогнать все четыре гейта до правок и зафиксировать вывод. Без базы любое падение
невозможно атрибутировать.

**Гейт этапа:** четыре команды из `verification.md` — вывод сохранён.

## Этап 1. Enum'ы и локализация

- `TaskType::CollectBuilding = 'collect_building'`.
- `app/Enums/BuildingClickMode.php`: `Auto`, `Collectible`, `QuestTrigger`.
- `app/Enums/CollectibleKind.php`: `Normal = 0`, `Event = 1`.
- Ключи в `lang/{en,ru,uk}/ui.php` и `lang/{en,ru,uk}/tasks.php` по таблице `design.md` §8.
- `php artisan tso:lang:export-frontend`.

**Гейт:** `LangImportCommandTest` и `LegacyLangRemovalTest` зелёные, все три локали симметричны.

## Этап 2. Allow-list и решающая логика

- `config/game.php` → `collectibles.clickable_patterns` с предупреждающим комментарием.
- `ClickableBuildingRegistry` + биндинг в `TaskServiceProvider`.
- `BuildingClickDecision`, `BuildingClickResolver`, `BuildingNotClickableException`.
- `tests/Unit/ClickableBuildingRegistryTest.php`.
- `tests/Unit/BuildingClickResolverTest.php` — все пять строк таблицы решений `design.md` §4
  плюс пограничные: пустое имя, другой регистр, битая регулярка в конфиге,
  пустой конфиг (→ всё уходит в `QuestTrigger`).

Это центральный этап безопасности. Он выполняется до того, как в коде появится
возможность вообще отправить команду из нового хендлера.

**Гейт:** оба unit-теста зелёные, phpstan без новых записей в baseline.

## Этап 3. Транспорт квест-триггера

- `TsoAmfService`: `CMD_QUEST_TRIGGER = 100`, `QUEST_STACK_BUILDING_SELECTED = 2`,
  метод `sendBuildingSelectedQuestTrigger()` с ссылками на строки клиента в комментариях.
- Расширить `tests/Feature/TsoAmfPayloadSnapshotTest.php`: форма пакета
  `{type: 2, grid: 0, endGrid: 0, data: <grid>}` и `commandType = 100`.
- Добавить в тот же тест утверждение, что форма `collectCollectible()` НЕ изменилась.

**Гейт:** `TsoAmfPayloadSnapshotTest` зелёный; старый snapshot не тронут.

## Этап 4. Хендлер

- `CollectBuildingHandler` по алгоритму `design.md` §6.
- Регистрация в `TaskServiceProvider`.
- Обработка `BuildingNotClickableException` в `app/Exceptions/Handler.php` по образцу
  `PickupsUnavailableException`.

**Гейт:** `SchedulerArchitectureTest`, `DomainExceptionHandlingTest`,
`TaskExecutionCharacterizationTest` зелёные.

## Этап 5. Валидация и Feature-тесты

- `ScheduledTaskRequest`: тип в `isBuildingTask()` и в building-ветке `sequenceRules()`;
  правило `payload.mode => nullable|string|in:auto,collectible,quest_trigger`.
- `tests/Feature/ScheduledTaskCollectBuildingTest.php`:
  1. `test_collectible_mode_sends_command_65_with_grid`
  2. `test_quest_trigger_mode_sends_command_100_with_grid_in_data`
  3. `test_quest_trigger_mode_never_sends_destruct_command`
  4. **`test_collectible_mode_on_production_building_sends_nothing`** (главный тест безопасности)
  5. `test_auto_mode_falls_back_to_quest_trigger_for_unknown_building`
  6. `test_missing_building_is_skipped_without_failure`
  7. `test_quest_trigger_error_551_is_skipped`
  8. `test_session_error_1005_is_rethrown`
  9. `test_zone_error_raises_game_server_error`
  10. `test_request_rejects_missing_grid_and_unknown_mode`
  11. `test_sequence_step_uses_the_same_handler`
- Добавить кейс в `ApiContractTest`.

**Гейт:** все одиннадцать тестов зелёные + `ScheduledTaskRegressionTest` без изменений.

## Этап 6. Фронтенд

Шесть точечных правок в `Tasks.vue` по `design.md` §7. Никаких новых компонентов,
модалок и правки CSS.

**Гейт:** `npm run build` зелёный; визуальный дифф — одна строка в дропдауне.

## Этап 7 (опционально). Подсказка из пула квестов

- `QuestTriggerBuildingProvider` и `GET /api/game/clickable-buildings` под `auth:sanctum`.
- Подсветка таких зданий в существующей модалке выбора.
- Отказ или таймаут эндпоинта не ломает форму (ADR-8).

Выполняется только после того, как этапы 1–6 подтверждены на живом аккаунте.

## Этап 8. Закрытие

- Все четыре гейта зелёные, `phpstan-baseline.neon` не вырос.
- Заполнить таблицу гейтов и ручные сценарии в `verification.md`.
- Закрыть или перенести вопросы Q-1…Q-4.

## Риски

| Риск | Вероятность | Влияние | Митигация |
| --- | --- | --- | --- |
| Ошибка в allow-list → снос здания | Низкая | **Критическое, необратимое** | INV-1, этап 2 до хендлера, тест №4, ручной сценарий S-1 первым |
| `data` нужен строкой, а не `int` | Средняя | Низкое | Сквозное логирование ответа, живой дамп (Q-3) |
| Клик вне квестового окна → `551` | Высокая | Низкое | Трактуется как скип, а не ошибка (FR-8) |
| Здание сменилось на grid после создания задачи | Средняя | Высокое | ADR-10: сверка только по свежей зоне |
| Лишний трафик при больших сериях | Средняя | Среднее | Существующие задержки шагов + follow-up F-3 |

## Definition of Done

- [ ] Все FR-1…FR-10 реализованы
- [ ] Инварианты INV-1…INV-6 покрыты тестами
- [ ] Критерии AC-1…AC-9 выполнены
- [ ] Ручной сценарий безопасности S-1 пройдён ДО сценариев успешного пути
- [ ] Гейты зелёные, baseline не вырос
- [ ] Визуальный дифф — один пункт дропдауна
- [ ] `collect_pickups` работает без изменений
