# Design: collect_building (revision 2)

## 1. Поток выполнения

```
Tasks.vue (существующая модалка выбора зданий)
  -> POST /api/tasks
  -> ScheduledTaskRequest (buildingRules + правило mode)
  -> ScheduledTask.payload
  -> ExecuteScheduledTaskJob
  -> SingleActionExecutor | SequenceStepExecutor
  -> TaskHandlerRegistry
  -> CollectBuildingHandler
       -> ZoneParserService::getZone()            // свежая зона, без кеша
       -> BuildingClickResolver::resolve()        // чистое решение: какая ветка
       -> TsoAmfService::collectCollectible()     // ветка 65
          | TsoAmfService::sendBuildingSelectedQuestTrigger()  // ветка 100
```

Границы ответственности:

| Слой | Знает о | НЕ знает о |
| --- | --- | --- |
| `Tasks.vue` | типе действия, `grid`, имени здания | командах, режимах, квестах |
| `ScheduledTaskRequest` | форме payload | игровых правилах |
| `BuildingClickResolver` | правилах выбора ветки | HTTP, AMF, БД |
| `CollectBuildingHandler` | оркестрации и ошибках | байтах протокола |
| `TsoAmfService` | командах и форме `dServerAction` | задачах и режимах |

Ключевое архитектурное решение: **решение о ветке вынесено из хендлера в чистый
объект `BuildingClickResolver`** без сети и состояния. Именно эта функция решает,
отправится ли потенциально разрушительная команда, и она тестируется unit-тестами
исчерпывающе, без моков и без базы.

## 2. Новые и изменяемые файлы

| Файл | Статус | Назначение |
| --- | --- | --- |
| `app/Enums/TaskType.php` | изменён | `case CollectBuilding = 'collect_building'` |
| `app/Enums/BuildingClickMode.php` | новый | `Auto`, `Collectible`, `QuestTrigger` |
| `app/Enums/CollectibleKind.php` | новый | `Normal = 0`, `Event = 1` |
| `app/Services/Game/ClickableBuildingRegistry.php` | новый | allow-list имён коллекций |
| `app/Services/Game/BuildingClickResolver.php` | новый | чистое решение о ветке |
| `app/Services/Game/BuildingClickDecision.php` | новый | иммутабельный результат решения |
| `app/Exceptions/BuildingNotClickableException.php` | новый | отказ allow-list |
| `app/Services/Tasks/Handlers/CollectBuildingHandler.php` | новый | оркестрация |
| `app/Services/TsoAmfService.php` | изменён | `sendBuildingSelectedQuestTrigger()` |
| `app/Providers/TaskServiceProvider.php` | изменён | регистрация хендлера и биндинги |
| `app/Http/Requests/Tasks/ScheduledTaskRequest.php` | изменён | тип в building-ветках + правило `mode` |
| `config/game.php` | изменён | `collectibles.clickable_patterns` |
| `lang/{en,ru,uk}/{ui,tasks}.php` | изменёны | ключи локализации |
| `resources/js/views/Tasks.vue` | изменён | 6 точек касания, см. §7 |

Новых миграций, моделей и эндпоинтов в обязательном объёме нет.

## 3. Конфиг

```php
// config/game.php
'collectibles' => [
    // ВНИМАНИЕ: этот список разрешает отправку команды сноса (65).
    // Любое лишнее имя здесь = риск безвозвратно снести здание игрока.
    'clickable_patterns' => [
        ['pattern' => '/^Collectible.+Building$/i', 'kind' => 0],
        ['pattern' => '/^StarfallStarDust.*$/i',    'kind' => 1],
    ],
],
```

Конфиг — единственное место, где расширяется разрешённое множество для опасной
ветки. Для ветки `100` конфиг не нужен вовсе — и это сознательно: никакого
ручного списка праздничных зданий поддерживать не придётся.

## 4. Решающие объекты

```php
final readonly class BuildingClickDecision
{
    public function __construct(
        public BuildingClickMode $mode,        // Collectible | QuestTrigger
        public int $grid,
        public string $buildingName,
        public ?CollectibleKind $kind,         // только для Collectible
        public string $reason,                 // для логов и аудита
    ) {}
}

final readonly class BuildingClickResolver
{
    public function __construct(private ClickableBuildingRegistry $registry) {}

    /**
     * @param array<string, mixed> $building запись из свежей зоны
     * @throws BuildingNotClickableException если явно запрошен Collectible, но имя не разрешено
     */
    public function resolve(
        BuildingClickMode $requested,
        int $grid,
        string $buildingName,
    ): BuildingClickDecision;
}
```

Таблица решений — полная и исчерпывающая:

| `requested` | Имя в allow-list | Результат |
| --- | --- | --- |
| `Auto` | да | `Collectible`, `reason = allowlist_match` |
| `Auto` | нет | `QuestTrigger`, `reason = auto_fallback_safe` |
| `Collectible` | да | `Collectible`, `reason = explicit_allowlist_match` |
| `Collectible` | нет | **исключение**, команда не шлётся |
| `QuestTrigger` | любое | `QuestTrigger`, `reason = explicit_quest_trigger` |

Обратите внимание на асимметрию: в `Auto` неизвестное имя падает в безопасную
ветку, а не в опасную. Это прямое воплощение fail-safe: ошибка классификации
стоит лишний no-op вместо снесенного здания.

```php
final readonly class ClickableBuildingRegistry
{
    /** @param list<array{pattern: string, kind: int}> $patterns */
    public function __construct(private array $patterns) {}

    public function classify(string $buildingName): ?CollectibleKind;
    public function isClickable(string $buildingName): bool;
    /** @return list<string> для подсказки в UI */
    public function patterns(): array;
}
```

Невалидная запись конфига игнорируется с warning-логом: опечатка в регулярке не должна
ни расширять разрешённое множество, ни ронять приложение.

## 5. Новый метод AMF

```php
public function sendBuildingSelectedQuestTrigger(Account $account, int $grid): mixed
{
    // COMMAND.QUEST_TRIGGER = 100 (client_scripts.txt:69425)
    // SERVER_STACK_BUILDING_SELECTED = 2 (client_scripts.txt:62956)
    // Отличие от ветки 65: grid едет в data, поле grid остаётся нулём.
    return $this->sendServerCall(
        $account,
        self::CMD_QUEST_TRIGGER,
        $this->buildServerAction(self::QUEST_STACK_BUILDING_SELECTED, 0, 0, $grid),
        targetZoneId: (int) $account->dso_auth_user,
    );
}
```

Новые константы: `CMD_QUEST_TRIGGER = 100`, `QUEST_STACK_BUILDING_SELECTED = 2`.
`buildServerAction()` уже принимает `mixed $data`, так что сигнатура не меняется.
Существующий `collectCollectible()` НЕ трогаем вовсе — его поведение зафиксировано
`TsoAmfPayloadSnapshotTest` и используется `collect_pickups`.

Открытый технический вопрос (Q-3 в `verification.md`): чем именно сериализовать `data` —
`int` или строкой. В AS3 это `Object`, куда кладётся результат `GetGrid():int`, поэтому
первая гипотеза — `int`. Подтверждается только живым дампом.

## 6. Хендлер

```php
final readonly class CollectBuildingHandler implements TaskActionHandlerInterface
{
    public function __construct(
        private ZoneParserService $zones,
        private TsoAmfService $amf,
        private BuildingClickResolver $resolver,
        private GameErrorResolver $errors,
    ) {}

    public function supports(string $actionType): bool;
    public function handle(Account $account, array $payload): string;
}
```

Алгоритм `handle()`:

1. `grid` из payload; `<= 0` → `InvalidTaskTypeException` (баг валидации, не игровая ситуация).
2. `mode` из payload → `BuildingClickMode::tryFrom() ?? Auto`.
3. Свежая зона; `errorCode != 0` → `GameServerErrorException`.
4. Поиск здания по `grid`; нет — вернуть `tasks.building_collect.not_found` (скип).
5. `resolver->resolve(...)` — единая точка решения, логируется `reason`.
6. `match ($decision->mode)` → один из двух методов AMF.
7. Извлечение кода ошибки; `1005`/`1012` — проброс; `551` и прочие — скип с warning.
8. Строка результата по FR-7.

Ни одного вызова AMF до шага 6. Шаг 5 — единственный шлюз к опасной команде.

## 7. Изменения во фронтенде

Ровно шесть точек в `resources/js/views/Tasks.vue`, по аналогии с `collect_pickups`:

1. Пункт дропдауна действий: `🎁 t('tasks.action.collect_building')`.
2. `iconMap`: `collect_building: '🎁'`.
3. `labelMap` и `typeLabelMap`.
4. `onStepActionTypeChange()`: новый тип трактуется как building-действие — та же модалка выбора.
5. Сборка шага: `payload = { grid, building_name, mode: 'auto' }`.
6. Чип шага в сводке серии — имя здания, как у `stop_production`.

Режим в UI НЕ показывается и не выбирается: всегда отправляется `auto`. Это
намеренно: оператор не должен знать о двух игровых механиках, а возможность вручную
выбрать `collectible` остаётся только на уровне API для диагностики и тестов.
Фактический визуальный дифф: одна новая строка в списке действий.

## 8. Локализация

| Ключ | ru |
| --- | --- |
| `ui.tasks.action.collect_building` | Собрать с здания |
| `ui.tasks.type_label.collect_building` | Сбор с здания |
| `tasks.building_collect.collected` | Здание: собрано :name (grid :grid) |
| `tasks.building_collect.gift_received` | Здание: подарок получен :name (grid :grid) |
| `tasks.building_collect.not_found` | Здание: на сетке :grid нечего собирать |
| `tasks.building_collect.nothing_to_collect` | Здание: награда пока недоступна :name |
| `tasks.error.building_not_clickable` | Здание :name нельзя собрать кликом |

Аналогичные ключи в `en` и `uk`. Генерированные JSON — только командой экспорта.

## 9. Опционально: пул квестов как источник подсказки

Отдельный этап, не входящий в критический путь:

```php
final readonly class QuestTriggerBuildingProvider
{
    /** @return list<string> имена зданий с активным триггером buildingselected */
    public function forAccount(Account $account): array;
}
```

Реализация: `QUEST_TRIGGER` с `dServerAction{type: 4, data: null}`
(`SERVER_STACK_GET_LATEST_QUEST_LIST`), затем из `dQuestPoolVO.mQuestVO_vector` берутся
`mQuestDefinition.questTriggers_vector` и фильтруются по `type == 1` (`TYPE_BUILDING`) и
`condition == CONDITION_SELECTED`; имя здания — `name_string`.

Это даёт точный перечень «где сейчас есть подарок» и снимает потребность в
`collections.xml` и в ручных шаблонах для этой ветки. Но:

- это только **подсказка UI**, не шлюз безопасности;
- отказ или таймаут этого запроса не блокирует ни форму, ни выполнение задачи;
- в ветке `65` он НЕ используется вообще: там решает только allow-list.

## 10. Соответствие конституции

- Один хендлер = одно действие; регистрация через `TaskHandlerRegistry`, без `match` в исполнителях.
- `final`, `readonly`, `declare(strict_types=1)`, DI через конструктор, фасад `Log` — как в остальном проекте.
- Доменные исключения вместо произвольных строк; тексты — только из `lang`.
- Никаких номеров команд и имён классов клиента в ответах API и в UI.
