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
| `app/Services/Game/QuestTriggerBuildingProvider.php` | новый | чтение пула квестов (FR-11) |
| `app/Services/Game/ClickableBuildingListService.php` | новый | сводит зону + allow-list + доступность |
| `app/Services/Game/ClickableBuildingDto.php` | новый | строка списка для UI |
| `app/Http/Controllers/Api/ClickableBuildingController.php` | новый | эндпоинт FR-12 |
| `app/Http/Resources/ClickableBuildingResource.php` | новый | формат ответа |
| `routes/api.php` | изменён | один новый GET-маршрут |
| `resources/js/views/Tasks.vue` | изменён | 8 точек касания, см. §7 |

Новых миграций и моделей нет. Новый эндпоинт ровно один, только на чтение (FR-12);
существующие контракты не меняются (INV-3).

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
7. В существующей модалке выбора зданий — бейдж типа и доступности в строке списка:
   `🧺` коллекция, `🎁` подарок доступен, приглушённый `🎁` — подарка сейчас нет,
   без бейджа — неизвестно или здание не относится к механике (FR-11).
8. Там же — один чекбокс «только доступные», по умолчанию выключен; фильтрует только
   отображение и никогда не влияет на payload.

Данные для пунктов 7–8 берутся одним запросом к эндпоинту FR-12 при открытии
модалки. Ошибка запроса — бейджи не рисуются, модалка работает ровно как сейчас
(AC-11). Ни одного нового компонента и ни одного нового CSS-класса не добавляется.

Режим в UI НЕ показывается и не выбирается: всегда отправляется `auto`. Это
намеренно: оператор не должен знать о двух игровых механиках, а возможность вручную
выбрать `collectible` остаётся только на уровне API для диагностики и тестов.
Фактический визуальный дифф: одна новая строка в списке действий, бейдж в строке
списка зданий и один чекбокс-фильтр.

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

## 9. Отображение зданий, доступных для выбора (FR-11, FR-12)

Модалка выбора зданий с мультивыбором уже реализована (`task-planner-multi-select`),
поэтому задача сводится к **обогащению строк списка двумя полями**, а не к
новому интерфейсу.

### 9.1. Классы

```php
final readonly class QuestTriggerBuildingProvider
{
    /**
     * Имена зданий с активным триггером buildingselected.
     * null — пул квестов недоступен, доступность неизвестна (не пустой список!).
     * @return list<string>|null
     */
    public function forAccount(Account $account): ?array;
}

final readonly class ClickableBuildingListService
{
    /** @return list<ClickableBuildingDto> */
    public function forAccount(Account $account): array;
}

final readonly class ClickableBuildingDto
{
    public function __construct(
        public int $grid,
        public string $buildingName,
        public string $kind,        // collectible | quest_gift | none
        public ?bool $available,    // null = неизвестно
    ) {}
}
```

Реализация `QuestTriggerBuildingProvider`: `QUEST_TRIGGER` с `dServerAction{type: 4, data: null}`
(`SERVER_STACK_GET_LATEST_QUEST_LIST`), затем из `dQuestPoolVO.mQuestVO_vector` берутся
`mQuestDefinition.questTriggers_vector` и фильтруются по `type == 1` (`TYPE_BUILDING`) и
`condition == CONDITION_SELECTED`; имя здания — `name_string`. Квесты с
`mQuestMode >= QUEST_MODE_DEACTIVATED` отбрасываются — так же, как это делает
`ContainsTriggerCondition()` в клиенте (`client_scripts.txt:57393`).

### 9.2. Классификация строки списка

| `kind` | Как определяется | `available` |
| --- | --- | --- |
| `collectible` | Имя в allow-list (`ClickableBuildingRegistry`) | `true`: коллекция есть в зоне — значит собирается |
| `quest_gift` | Имя есть в списке от `QuestTriggerBuildingProvider` | `true` |
| `quest_gift` | Имя в списке отсутствует, но список получен | `false` |
| `none` | Ни то, ни другое | `null` |
| любое | `forAccount()` вернул `null` (пул недоступен) | `null` для всех `quest_gift` |

Отличать `false` от `null` обязательно (ADR-12). Сведённые в одно значение
«подарка нет» и «не знаю» — классический способ получить жалобу «интерфейс врёт,
подарок там был».

### 9.3. Эндпоинт

`GET /api/game/clickable-buildings?account_id=…` → `ClickableBuildingResource` через
`AnonymousResourceCollection`, как в остальном проекте. Контракт — FR-12.

Границы:

- только чтение (INV-8): загрузка зоны + чтение пула квестов, ничего больше;
- ошибка пула квестов ловится внутри сервиса и превращается в `available: null` с `200`;
- кеш ответа короткий (порядка 30 с) и только для отображения; решение в хендлере
  кеш НЕ использует никогда (ADR-10);
- после успешного выполнения `collect_building` кеш для аккаунта сбрасывается, иначе
  собранный подарок остался бы «доступным» в интерфейсе (AC-10).

### 9.4. Почему рантайм, а не статический список

В `globals.xml` `FlyingHouse` — обычное здание без любых признаков кликабельности, а
атрибут `destroyOnClick` стоит только у служебных объектов и НЕ стоит у коллекций
(см. `data-sources.md`, ADR-13). Любой статический список «праздничных зданий» был бы
догадкой по именам и устаревал бы каждый сезонный ивент. Пул квестов отвечает
на вопрос точно и без сопровождения.

## 10. Соответствие конституции

- Один хендлер = одно действие; регистрация через `TaskHandlerRegistry`, без `match` в исполнителях.
- `final`, `readonly`, `declare(strict_types=1)`, DI через конструктор, фасад `Log` — как в остальном проекте.
- Доменные исключения вместо произвольных строк; тексты — только из `lang`.
- Никаких номеров команд и имён классов клиента в ответах API и в UI.
