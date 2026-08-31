# Implementation plan: build_mine / upgrade_mine

Ревизия 2. Шаги выполняются строго по порядку. После каждого шага запускается его гейт.
Не переходи к следующему шагу, пока гейт предыдущего красный.

Проект: PHP 8.5 / Laravel 12 / Vue 3. Корень: `C:\OSPanel\home\admin`.

## Что исправлено в ревизии 2

Ревизия 1 содержала ошибки, которые ломали код при буквальном исполнении. Список,
чтобы они не вернулись:

1. **Сигнатура `recursive_extract`.** Существующие вызовы передают `visited`
   позиционно девятым аргументом. Вставка новых параметров перед `visited`
   привязала бы `set` к `deposits` и обнулила защиту от циклов. Теперь §1.1
   требует именованных аргументов и перечисляет все шесть точек вызова.
2. **Поля очереди стройки были выдуманы.** `dPlayerVO` разбирается по белому списку
   атрибутов, а `GetQueue_vector()` / `GetTotalAvailableSlots()` из юзерскрипта —
   методы клиентской модели, а не поля VO. Добавлен обязательный шаг разведки §1.0.
3. **Загрузка зоны.** Указан был только `ZoneParserService`; реально нужны два
   вызова: `TsoAmfService::getZone()` + `ZoneParserService::parse()`.
4. **В скелете обработчика отсутствовал `supports()`** — обязательный метод
   `TaskActionHandlerInterface`.
5. **`extractErrorCode()` не возвращает `null`** — проверка `!== null` была мёртвой,
   а сбой разбора ответа молча трактовался как успех. См. §5.1.
6. **Не были учтены существующие тесты**, перечисляющие случаи `TaskType`: они
   упадут при добавлении двух case. См. §2.4.
7. **Не был упомянут `resources/js/components/tasks/BuildingPicker.vue`** — готовый
   компонент выбора цели, который надо переиспользовать вместо новой модалки.
8. Исправлены счётчики файлов и перекрёстные ссылки.

---

## Шаг 0. Подготовка

```bash
git checkout -b feature/build-mine
php artisan test
```

База должна быть зелёной ДО правок. Если тесты падают на чистой ветке — остановись
и сообщи об этом, не начинай реализацию.

Миграция БД не нужна: `scheduled_tasks.task_type` — обычная строка
(`database/migrations/2026_07_09_170001_create_scheduled_tasks_table.php:14`,
`$table->string('task_type')`). Никаких CHECK-ограничений и Postgres-enum нет.

---

## Шаг 1. Парсер зоны: залежи и очередь стройки

Файл: `storage/app/parse_zone.py` (800 строк, 32 КБ).

### 1.0. Разведка полей (обязательно, до правок)

Имена полей залежи известны из `client_scripts.txt:54816-54860`. Имена полей
очереди стройки — НЕ известны: в юзерскрипте это методы клиентской модели
(`GetQueue_vector()`, `GetTotalAvailableSlots()`), а не поля серверного VO.

Сначала выясни фактические имена на живом дампе. Временный скрипт
`storage/app/inspect_zone_attrs.py`:

```python
import sys, json
from pyamf import remoting

seen = {}

def walk(obj, visited=None):
    if visited is None:
        visited = set()
    if id(obj) in visited:
        return
    visited.add(id(obj))
    name = type(obj).__name__
    alias = getattr(obj, 'alias', '') or name
    if hasattr(obj, '__dict__'):
        keys = sorted(obj.__dict__.keys())
        if 'Deposit' in str(alias) or 'Player' in str(alias) or 'Queue' in str(alias) or 'Zone' in str(alias):
            seen.setdefault(str(alias), set()).update(keys)
        for v in obj.__dict__.values():
            if v is not None:
                walk(v, visited)
    if hasattr(obj, 'source') and not isinstance(obj, (str, bytes)):
        try:
            if obj.source is not None:
                walk(obj.source, visited)
        except Exception:
            pass
    if isinstance(obj, (list, tuple)):
        for v in obj:
            if v is not None:
                walk(v, visited)
    if isinstance(obj, dict):
        for v in obj.values():
            if v is not None:
                walk(v, visited)

with open(sys.argv[1], 'rb') as f:
    env = remoting.decode(f.read())
for _, message in env.bodies:
    walk(getattr(message, 'body', message))

print(json.dumps({k: sorted(v) for k, v in seen.items()}, indent=2, ensure_ascii=False))
```

Запуск: `python storage/app/inspect_zone_attrs.py <дамп>`

Результат выпиши в `docs/spec/build-mine/data-sources.md` в новый раздел
«Фактические поля дампа». Только после этого пиши §1.3, подставив реальные имена.
Если поля очереди в дампе отсутствуют вообще — §1.3 не делается, `build_queue`
остаётся `None`, и это фиксируется записью в `docs/foundbugs.md`. PHP-сторона такой
исход обрабатывает корректно (ADR-7: нет данных = нет свободных слотов = отказ).
Временный скрипт после разведки удали.

### 1.1. Расширить сигнатуру и ВСЕ вызовы

Сейчас (строка 184):

```python
def recursive_extract(obj, buildings, specialists, buffs, resources, friends, players, zone_info, visited=None):
```

Станет:

```python
def recursive_extract(obj, buildings, specialists, buffs, resources, friends, players, zone_info, deposits, build_queue, visited=None):
```

**ОПАСНОСТЬ.** Существующие рекурсивные вызовы передают `visited` ПОЗИЦИОННО.
Если просто вставить два параметра перед `visited`, то `visited` (объект `set`)
привяжется к `deposits`, а сам `visited` станет `None` — защита от циклических
ссылок отключится, и на первом же циклическом графе AMF будет бесконечная
рекурсия. Поэтому все внутренние вызовы переводятся на именованный аргумент.

Всего шесть точек вызова, все шесть обязательны:

| № | Где | Было | Станет |
| --- | --- | --- | --- |
| 1 | ветка `if hasattr(obj, 'source')` внутри `recursive_extract` (многострочный вызов) | `..., zone_info, visited` | `..., zone_info, deposits, build_queue, visited=visited` |
| 2 | цикл `for key, value in obj.__dict__.items()` | `..., zone_info, visited` | то же |
| 3 | цикл по `list / tuple / ASObject` | `..., zone_info, visited` | то же |
| 4 | цикл `for key, value in obj.items()` (ветка `isinstance(obj, dict)`) | `..., zone_info, visited` | то же |
| 5 | `main()`, `recursive_extract(message.body, ...)` | `..., zone_info)` | `..., zone_info, deposits, build_queue)` |
| 6 | `main()`, `recursive_extract(message, ...)` | `..., zone_info)` | `..., zone_info, deposits, build_queue)` |

Проверка полноты правки:

```bash
python -c "import re,io; s=io.open(r'storage/app/parse_zone.py',encoding='utf-8').read(); print(len(re.findall(r'recursive_extract\(', s)))"
```

Ожидается `7` (одно определение + шесть вызовов). Каждый из шести вызовов обязан
содержать `deposits, build_queue`.

### 1.2. Новая ветка для залежей

Вставить в цепочку `elif` внутри `recursive_extract` — после ветки `dBuildingVO`
(она заканчивается на `buildings.append(building)`) и перед
`elif 'dSpecialistVO' in full_name`. Отступ — 8 пробелов, как у соседних `elif`.

```python
        elif 'dDepositVO' in full_name or 'DepositVO' in full_name:
            deposit = {}
            for attr in ['gridIdx', 'name_string', 'name', 'amount', 'maxAmount',
                         'accessible', 'refillable', 'emptied', 'depositGroupdId']:
                val = None
                if hasattr(obj, attr):
                    val = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    val = obj[attr]

                if val is not None:
                    deposit[attr] = val

            grid_raw = deposit.get('gridIdx')
            name_raw = deposit.get('name_string') or deposit.get('name')

            if isinstance(name_raw, bytes):
                name_raw = name_raw.decode('utf-8', errors='replace')

            try:
                grid_val = int(grid_raw) if grid_raw is not None else 0
            except (TypeError, ValueError):
                grid_val = 0

            def _int_or(value, fallback=0):
                try:
                    return int(value)
                except (TypeError, ValueError):
                    return fallback

            if grid_val > 0 and name_raw:
                deposits.append({
                    'grid': grid_val,
                    'name': str(name_raw),
                    'amount': _int_or(deposit.get('amount')),
                    'max_amount': _int_or(deposit.get('maxAmount')),
                    'accessible': (_int_or(deposit['accessible'], -1)
                                   if deposit.get('accessible') is not None else None),
                    'refillable': bool(deposit.get('refillable') or False),
                    'emptied': _int_or(deposit.get('emptied')),
                })
```

Замечания:

- имя поля в клиенте — `depositGroupdId`, с опечаткой самой игры
  (`client_scripts.txt:54816-54860`). Не «исправлять»;
- `bytes` декодируются явно: без этого `str(b'IronOre')` дало бы строку
  `"b'IronOre'"`, и сверка с ключами каталога перестала бы работать;
- грид `0` отбрасывается так же, как в `pickups_from_buildings()`;
- значения приводятся к скалярам здесь, поэтому в §1.4 `make_serializable` для
  `deposits` не нужен.

### 1.3. Очередь стройки

Только после §1.0, с реальными именами полей. Ветка `dPlayerVO` разбирает атрибуты
по белому списку, поэтому недостаточно «добавить рядом» — нужно и в список, и в
`build_queue`.

А. В список атрибутов ветки `dPlayerVO` добавить найденные в §1.0 имена, например
`currentMaximumBuildingsCountAll` там уже есть — добавляются только новые.

Б. Сразу после `players.append(player)` в той же ветке:

```python
                # ИМЕНА ПОЛЕЙ ПОДСТАВИТЬ ИЗ РАЗВЕДКИ §1.0.
                for used_attr in ['<имя_из_разведки>']:
                    if used_attr in player:
                        build_queue['used'] = _queue_len(player[used_attr])
                        break

                for total_attr in ['<имя_из_разведки>']:
                    if total_attr in player:
                        build_queue['total'] = int(player[total_attr] or 0)
                        break
```

Где `_queue_len` — модульная функция рядом с `_as_items`:

```python
def _queue_len(container):
    """Length of a build-queue container (list or Flex ArrayCollection)."""
    return len(_as_items(container))
```

Если имён не нашлось — ветка не пишется совсем. Не изобретать имена «на всякий
случай»: запись с выдуманным полем даст `total = 0`, что молча заблокирует всю
фичу отказами `build_queue_full` и будет выглядеть как баг логики.

### 1.4. Инициализация и вывод

В `main()`, в блок где создаются `buildings = []` … `zone_info = {}`
(непосредственно перед `error_code = 0`):

```python
    deposits = []
    build_queue = {}
```

В итоговый словарь `result` добавить два ключа в конец, после `'visitors'`, не
меняя и не переупорядочивая существующие:

```python
        'deposits': deposits,
        'build_queue': {
            'used': int(build_queue.get('used', 0)),
            'total': int(build_queue.get('total', 0)),
        } if build_queue else None,
```

### Гейт шага 1

```bash
python storage/app/parse_zone.py <путь-к-дампу-зоны> > zone.json
```

- [ ] команда завершилась без исключений (главный признак того, что §1.1 сделан целиком);
- [ ] `deposits` непустой, имена руд совпадают с ключами каталога буквально, без `b'...'`;
- [ ] `build_queue` либо корректный объект, либо `null` — но не `{"used":0,"total":0}` с выдуманных полей;
- [ ] все существующие ключи (`buildings`, `resources`, `pickups`, `errorCode`, `visitors`, …) на месте и в прежнем формате.

---

## Шаг 2. Конфиг, типы задач, существующие тесты

### 2.1. `config/game.php`

Добавить новую секцию рядом с `collectibles`, в том же стиле с комментарием-
предупреждением:

```php
    /*
    |--------------------------------------------------------------------------
    | Buildings
    |--------------------------------------------------------------------------
    |
    | WARNING. `number` — это игровой номер здания, который уходит в команду 50.
    | Ошибка в номере = построено не то здание и безвозвратно потрачены ресурсы
    | игрока. Источник номеров: docs/references/icons.xml (атрибут id).
    | Источник пар «руда -> шахта»: docs/references/globals.xml
    | (атрибут restrictPlacingToDeposit).
    | Процедура сверки описана в docs/spec/build-mine/data-sources.md.
    |
    */
    'buildings' => [
        'mines' => [
            'BronzeOre' => ['mine' => 'BronzeMine', 'number' => 36, 'max_level' => 7],
            'Coal' => ['mine' => 'CoalMine', 'number' => 37, 'max_level' => 7],
            'GoldOre' => ['mine' => 'GoldMine', 'number' => 46, 'max_level' => 7],
            'IronOre' => ['mine' => 'IronMine', 'number' => 50, 'max_level' => 7],
            'Salpeter' => ['mine' => 'SalpeterMine', 'number' => 63, 'max_level' => 7],
            'TitaniumOre' => ['mine' => 'TitaniumMine', 'number' => 69, 'max_level' => 7],
        ],
    ],
```

### 2.2. `app/Enums/TaskType.php`

Добавить два случая после `case CollectBuilding = 'collect_building';`:

```php
    case BuildMine = 'build_mine';
    case UpgradeMine = 'upgrade_mine';
```

`isSequence()` не трогать.

### 2.3. Enum причин

Создать `app/Enums/PlacementRejectionReason.php` и
`app/Enums/UpgradeRejectionReason.php` ровно с теми случаями, что в `design.md` §5.
Ни больше, ни меньше.

### 2.4. Починить существующие тесты (не пропускать)

Добавление case в `TaskType` ломает тесты, которые перечисляют типы. Файлы,
которые придётся обновить (найдены поиском по `collect_building`):

```
tests/Unit/EnumsTest.php                        перечень case TaskType
tests/Unit/TaskHandlerRegistryTest.php          перечень поддерживаемых типов
tests/Feature/ApiContractTest.php               контракт ответа со списком типов
```

В каждом: добавить `build_mine` и `upgrade_mine` в ожидаемые наборы. Если тест
сверяет количество — увеличить его на два. Ни один существующий ожидаемый тип не
удалять и не переименовывать (INV-5).

### Гейт шага 2

```bash
php artisan config:clear
php artisan test --filter=Enums
php artisan test --filter=ApiContract
./vendor/bin/pint --test
```

---

## Шаг 3. Домен и тесты домена

Создать в таком порядке (полные сигнатуры — в `design.md` §2 и §5-§8):

1. `app/Services/Game/Mines/MineDefinition.php`
2. `app/Services/Game/Mines/Contracts/MineCatalogInterface.php`
3. `app/Services/Game/Mines/ConfigMineCatalog.php`
4. `app/Services/Game/Mines/DepositSnapshot.php`
5. `app/Services/Game/Mines/BuildingSnapshot.php`
6. `app/Services/Game/Mines/BuildQueueSnapshot.php`
7. `app/Services/Game/Mines/BuildQueueBudget.php`
8. `app/Services/Game/Mines/ZoneSnapshot.php`
9. `app/Services/Game/Mines/PlacementDecision.php`
10. `app/Services/Game/Mines/UpgradeDecision.php`
11. `app/Services/Game/Mines/MinePlacementPolicy.php`
12. `app/Services/Game/Mines/MineUpgradePolicy.php`

Правила для всех двенадцати файлов:

- первая строка после `<?php` — `declare(strict_types=1);`;
- `final readonly class`, кроме интерфейса;
- в файлах 1-2 и 4-12 ни одного фасада (`Log`, `Cache`, `config`) и ни одного
  `use Illuminate\...`. Чтение конфига разрешено только в `ConfigMineCatalog`
  (файл 3), и только через инъекцию массива в конструктор — не через `config()`
  внутри методов;
- типизированные константы в стиле проекта: `private const int`, `private const array`;
- PHPDoc `@param array<...>` / `@return list<...>` на всех массивных параметрах —
  иначе phpstan потребует baseline (AC-12).

Вместе с ними сразу написать тесты `verification.md` §1-§4. Они не требуют ни базы
данных, ни HTTP.

### Гейт шага 3

```bash
php artisan test --filter=Mine
./vendor/bin/phpstan analyse
```

---

## Шаг 4. Адаптеры: снапшот и шлюз

### 4.1. `TsoAmfService`

Файл: `app/Services/TsoAmfService.php`. Добавить два метода рядом с существующим
`stopProduction()`, в точности в том же стиле:

```php
public function buildBuilding(Account $account, int $buildingNumber, int $grid): string
{
    return $this->sendServerCall(
        $account,
        self::CMD_BUILD,
        $this->buildServerAction($buildingNumber, $grid, 0, null),
    );
}

public function upgradeBuilding(Account $account, int $grid): string
{
    return $this->sendServerCall(
        $account,
        self::CMD_UPGRADE,
        $this->buildServerAction(0, $grid, 0, null),
    );
}
```

Перед написанием сверь фактические сигнатуры `sendServerCall()` и
`buildServerAction()` в файле и подставь аргументы позиционно так, как это делает
`stopProduction()`. Константы `CMD_BUILD = 50` и `CMD_UPGRADE = 60` в классе уже
есть — новых не вводить. Другие методы файла не трогать.

### 4.2. Шлюз

`app/Services/Game/Mines/Contracts/MineCommandGatewayInterface.php` и
`app/Services/Game/Mines/AmfMineCommandGateway.php` — тонкие делегаты к §4.1.
Никакой логики решений в шлюзе.

### 4.3. Провайдер снапшота

`app/Services/Game/Mines/Contracts/ZoneSnapshotProviderInterface.php` и
`app/Services/Game/Mines/AmfZoneSnapshotProvider.php`.

Зону грузить ровно так, как это делает `CollectBuildingHandler::handle()` —
это ДВА вызова, а не один:

```php
$zoneAmf = $this->amf->getZone($account);
$zone = $this->zones->parse($zoneAmf);

$errorCode = (int) ($zone['errorCode'] ?? 0);
if ($errorCode !== 0) {
    throw new GameServerErrorException($errorCode, GameErrorResolver::getMessage($errorCode));
}
```

Зависимости конструктора: `ZoneParserService $zones`, `TsoAmfService $amf`.

Дальше собрать снапшот:

1. `DepositSnapshot` из `$zone['deposits']`, ключ массива — `grid`;
2. `BuildingSnapshot` из `$zone['buildings']`; грид —
   `(int) ($b['buildingGrid'] ?? $b['grid'] ?? 0)`; имя —
   `(string) ($b['buildingName_string'] ?? $b['buildingName'] ?? '')`
   (порядок именно такой: в `buildings` встречаются оба ключа);
   уровень — `(int) ($b['upgradeLevel'] ?? $b['level'] ?? 0)`;
   флаги — `(bool) ($b['isProductionActive'] ?? false)` и
   `(bool) ($b['upgradeIsInProgress'] ?? false)`;
3. `BuildQueueSnapshot` из `$zone['build_queue']`, при отсутствии или `null` —
   передать `null` (ADR-7);
4. записи с гридом `0` пропускать;
5. каждый элемент проверять `is_array()` перед обращением — в `buildings`
   попадают строки-заглушки вида `<circular reference ...>` от
   `make_serializable()`.

### Гейт шага 4

```bash
php artisan test --filter=Gateway
php artisan test --filter=Snapshot
./vendor/bin/phpstan analyse
```

Тесты обязаны проверять точную форму пакетов (`verification.md` §5).

---

## Шаг 5. Обработчики, регистрация, валидация, локализация

### 5.1. Обработчики

Создать `app/Services/Tasks/Handlers/BuildMineHandler.php` и
`app/Services/Tasks/Handlers/UpgradeMineHandler.php`. Образец —
`CollectBuildingHandler`: тот же namespace, `final readonly class ... implements
TaskActionHandlerInterface`, та же константа
`private const array SESSION_ERROR_CODES = [1005, 1012];`, тот же приватный
`extractErrorCode()`.

Интерфейс требует ДВА метода. `supports()` пропустить нельзя:

```php
public function supports(string $actionType): bool
{
    return $actionType === TaskType::BuildMine->value;
}

/**
 * @param  array<string, mixed>  $payload
 */
public function handle(Account $account, array $payload): string
```

Порядок в `handle()` (строго):

```php
$grid = (int) ($payload['grid'] ?? 0);
if ($grid <= 0) {
    throw new InvalidTaskTypeException('Invalid building grid parameter.', 422);
}

$zone = $this->zones->forAccount($account);      // может бросить GameServerErrorException
$decision = $this->policy->decide($zone, $grid); // для upgrade: + (int) ($payload['max_level'] ?? 0)

Log::info(sprintf(
    '[BuildMine] Account #%d: grid=%d, deposit="%s", mine="%s", number=%s, freeSlots=%d, allowed=%s, reason=%s',
    $account->id, $grid, $decision->depositName, $decision->mineName,
    $decision->definition?->buildingNumber ?? 'none', $decision->freeSlots,
    $decision->allowed ? 'yes' : 'no', $decision->reason->value
));

if (! $decision->allowed || $decision->definition === null) {
    return __('tasks.build_mine.rejected.'.$decision->reason->value, [...]);
}

$rawAmf = $this->gateway->buildMine($account, $decision->definition->buildingNumber, $grid);
$responseCode = $this->extractErrorCode($rawAmf);

if ($responseCode === 0) {
    MineTargetListService::clearCache((int) $account->id);

    return __('tasks.build_mine.built', ['name' => $decision->mineName, 'grid' => $grid]);
}

if (in_array($responseCode, self::SESSION_ERROR_CODES, true)) {
    throw new GameServerErrorException($responseCode, GameErrorResolver::getMessage($responseCode));
}

Log::warning("[BuildMine] Account #{$account->id}: grid {$grid} failed with game error {$responseCode}");

return __('tasks.build_mine.game_error', ['message' => GameErrorResolver::getMessage($responseCode)]);
```

Важные детали, которые легко испортить:

- `extractErrorCode()` возвращает `int`, никогда `null`. Проверка `!== null`
  бессмысленна — не писать её. Порядок ветвления именно такой, как выше:
  сначала `=== 0`, потом сессионные коды, потом остальное;
- у `extractErrorCode()` есть известное свойство: при исключении разбора он
  возвращает `0`, то есть сбой выглядит как успех. Для сбора коллектиблов это
  безвредно, для стройки — нет: мы отрапортуем «построено», не зная этого точно.
  Поведение сознательно сохраняется одинаковым с существующим кодом, но в
  `catch` добавляется `Log::warning('[BuildMine] unparseable response')`, чтобы
  такие случаи были видны в логах. Менять контракт `extractErrorCode()` в
  `CollectBuildingHandler` в рамках этой задачи запрещено;
- отказ политики — это `return` строки, а не исключение (ADR-12);
- решение логируется ВСЕГДА, включая отказы (FR-12);
- никаких дополнительных игровых проверок в обработчике. Если кажется, что нужна
  ещё одна проверка — она добавляется в политику и в enum причин.

### 5.2. Регистрация

Файл: `app/Providers/TaskServiceProvider.php`, метод `register()`.

1. Добавить три привязки интерфейс → реализация из `design.md` §9.
2. В существующий массив классов обработчиков добавить две строки:
   `BuildMineHandler::class,` и `UpgradeMineHandler::class,`.

Структуру реестра и `TaskHandlerRegistry` не менять.

### 5.3. Валидация

Файл: `app/Http/Requests/Tasks/ScheduledTaskRequest.php`. Три точки, все приватные
методы.

А. `isBuildingTask()` — добавить два значения в существующий список:

```php
    private function isBuildingTask(): bool
    {
        return in_array($this->taskTypeString(), [
            TaskType::StopProduction->value,
            TaskType::StartProduction->value,
            TaskType::CollectBuilding->value,
            TaskType::BuildMine->value,
            TaskType::UpgradeMine->value,
        ], true);
    }
```

Это даёт задачам шахт `payload.grid => required|integer|min:1` из
`buildingRules()`. Остальные ключи там `nullable`, поэтому лишнего не требуют.
Правило `payload.mode` для шахт не применяется, но и не мешает: его просто не
присылают.

Б. `buildingRules()` — добавить два ключа к существующему массиву:

```php
            'payload.deposit_name' => 'nullable|string|max:255',
            'payload.max_level' => 'nullable|integer|min:1|max:7',
```

`payload.grid` там уже есть — не дублировать.

В. `sequenceRules()` — НЕ добавлять типы шахт в существующий `in_array`, потому
что тот блок навязывает шагам `payload.mode` с набором значений сбора зданий.
Вместо этого добавить отдельный `if` внутри того же `foreach`, сразу после
существующего блока:

```php
            if (in_array($taskType, [TaskType::BuildMine->value, TaskType::UpgradeMine->value], true)) {
                $rules["payload.actions.{$index}.payload.grid"] = 'required|integer|min:1';
                $rules["payload.actions.{$index}.payload.deposit_name"] = 'nullable|string|max:255';
                $rules["payload.actions.{$index}.payload.building_name"] = 'nullable|string|max:255';
                $rules["payload.actions.{$index}.payload.max_level"] = 'nullable|integer|min:1|max:7';
            }
```

Остальные методы файла (`buffRules`, `pickupRules`, `specialistRules`,
`validateInterval`, `validateBuffPayloads`, `attributesForTask`) не трогать.

Замечание про `max:7`: семёрка здесь дублирует
`MineUpgradePolicy::DEFAULT_MAX_LEVEL`. Это осознанный компромисс — правила
валидации в проекте задаются строками. Если константа изменится, этот `max:7`
обязателен к синхронной правке; в `MineUpgradePolicy` над константой оставить
комментарий со ссылкой на этот файл.

### 5.4. Строки локализации

Файлы: `lang/en/tasks.php`, `lang/ru/tasks.php`, `lang/uk/tasks.php`.
Ключи (все три локали одновременно, одинаковый набор):

```
tasks.build_mine.built                                  :name (:grid)
tasks.build_mine.game_error                             :message
tasks.build_mine.rejected.no_deposit_at_grid            :grid
tasks.build_mine.rejected.unknown_deposit_type          :name
tasks.build_mine.rejected.deposit_empty                 :grid
tasks.build_mine.rejected.grid_occupied                 :grid
tasks.build_mine.rejected.deposit_not_accessible        :grid
tasks.build_mine.rejected.build_queue_full              -

tasks.upgrade_mine.upgraded                             :name :level (:grid)
tasks.upgrade_mine.game_error                           :message
tasks.upgrade_mine.rejected.no_building_at_grid         :grid
tasks.upgrade_mine.rejected.not_a_mine                  :name
tasks.upgrade_mine.rejected.max_level_reached           :name :level
tasks.upgrade_mine.rejected.upgrade_already_in_progress :grid
tasks.upgrade_mine.rejected.production_inactive         :grid
tasks.upgrade_mine.rejected.build_queue_full            -
```

Для каждого случая обоих enum обязан существовать ключ. Итоговая строка — не
длиннее 150 символов (`config('game.tasks.max_result_length')`).

### Гейт шага 5

```bash
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
```

---

## Шаг 6. Сервис списков, контроллеры, маршруты

### 6.1. Сервис

`app/Services/Game/Mines/MineTargetListService.php` — сигнатура в `design.md` §10.
Образец кэширования — `app/Services/Game/ClickableBuildingListService.php`: та же
константа TTL, те же статические `cacheKey()` и `clearCache()`.

Для каждой строки списка вызывать ТУ ЖЕ политику, что и обработчик, и переносить
`decision->reason->value` в поле `reason` (INV-4). Строки с `allowed = false` из
списка не выбрасывать: UI показывает их с причиной.

### 6.2. Контроллеры и ресурсы

Образец: `app/Http/Controllers/Api/ClickableBuildingController.php` и
`app/Http/Resources/ClickableBuildingResource.php`.

Создать:

- `app/Http/Controllers/Api/BuildableDepositController.php`
- `app/Http/Controllers/Api/UpgradableMineController.php`
- `app/Http/Resources/BuildableDepositResource.php`
- `app/Http/Resources/UpgradableMineResource.php`

Контроллер делает три вещи: валидирует `account_id`, вызывает сервис, возвращает
ресурс. Любое исключение загрузки зоны — лог и `200` с пустым `data` (FR-13).
Никаких игровых команд из контроллеров (INV-7).

### 6.3. Маршруты

Файл: `routes/api.php`. В блок `Route::middleware('auth:sanctum')->group(...)`,
рядом со строкой `/game/clickable-buildings`:

```php
Route::get('/game/buildable-deposits', [BuildableDepositController::class, 'index']);
Route::get('/game/upgradable-mines', [UpgradableMineController::class, 'index']);
```

### Гейт шага 6

```bash
php artisan route:list --path=game
php artisan test
```

---

## Шаг 7. Фронтенд

### 7.1. Переиспользовать существующий компонент

В проекте уже есть `resources/js/components/tasks/BuildingPicker.vue` — готовый
выбор цели по гриду для задач зданий. Сначала прочитай его целиком и оцени: он
расширяется пропсами (источник данных, колонки, признак недоступности) или нужен
второй похожий компонент `MineTargetPicker.vue` рядом с ним. Новую модалку внутри
`Tasks.vue` не писать.

### 7.2. `resources/js/views/Tasks.vue`

Ожидаемые правки — 6-8 точек:

1. Список типов действий: добавить `build_mine` и `upgrade_mine` с подписями из `ui`.
2. Две функции загрузки списков с новых эндпоинтов — по образцу существующего
   запроса списка кликабельных зданий.
3. Выбор цели через компонент из §7.1.
4. Строки с `buildable: false` / `upgradable: false` показываются неактивными с
   пояснением по `reason`.
5. Сборка payload: только `grid`, отображаемые имена и (для апгрейда) `max_level`.
   Номер здания не отправлять никогда (ADR-13).
6. Мультивыбор собирается в `sequence` с `delay_seconds: 1` на каждый шаг (FR-9).

### 7.3. Локализация UI

Новые ключи в `lang/en/ui.php`, `lang/ru/ui.php`, `lang/uk/ui.php`: названия двух
типов задач, заголовок выбора цели, подписи колонок (грид, руда, шахта, остаток,
уровень), тексты причин недоступности. Наборы ключей в трёх локалях должны
совпасть.

### Гейт шага 7

```bash
php artisan tso:lang:export-frontend
npm run build
```

---

## Шаг 8. Проверить фактическую задержку последовательности

FR-9 требует не менее 1000 мс между отправками. План опирается на то, что
`delay_seconds` шага соблюдается движком, но это НЕ проверено по коду.

Прочитай `app/Services/Tasks/Execution/SequenceStepExecutor.php` и убедись, что
`delay_seconds` реально применяется между шагами.

- Если применяется — задокументируй это одной строкой в `decisions.md` (ADR-6) со
  ссылкой на файл и метод. Больше ничего не делается.
- Если не применяется — НЕ добавлять `sleep` в обработчик (ADR-6 это прямо
  запрещает). Остановись и сообщи об этом: нужно отдельное решение.

---

## Шаг 9. Ручная проверка на тестовом аккаунте

Сценарий и чек-лист — `verification.md` §9. Обязательно на аккаунте, который не
жалко: команды `50` и `60` необратимы.

---

## Шаг 10. Финальные гейты

```bash
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
```

Дополнительно (AC-12):

- [ ] `phpstan-baseline.neon` не вырос;
- [ ] `.env` не тронут;
- [ ] `composer.json` и `package.json` не менялись;
- [ ] все три локали содержат одинаковый набор ключей;
- [ ] временный `storage/app/inspect_zone_attrs.py` из §1.0 удалён;
- [ ] временный `zone.json` из гейта шага 1 не закоммичен.

---

## Сводный список затрагиваемых файлов

### Новые: 21 класс + 4 HTTP-артефакта

```
app/Enums/PlacementRejectionReason.php
app/Enums/UpgradeRejectionReason.php
app/Services/Game/Mines/Contracts/MineCatalogInterface.php
app/Services/Game/Mines/Contracts/ZoneSnapshotProviderInterface.php
app/Services/Game/Mines/Contracts/MineCommandGatewayInterface.php
app/Services/Game/Mines/MineDefinition.php
app/Services/Game/Mines/ConfigMineCatalog.php
app/Services/Game/Mines/DepositSnapshot.php
app/Services/Game/Mines/BuildingSnapshot.php
app/Services/Game/Mines/BuildQueueSnapshot.php
app/Services/Game/Mines/BuildQueueBudget.php
app/Services/Game/Mines/ZoneSnapshot.php
app/Services/Game/Mines/AmfZoneSnapshotProvider.php
app/Services/Game/Mines/PlacementDecision.php
app/Services/Game/Mines/UpgradeDecision.php
app/Services/Game/Mines/MinePlacementPolicy.php
app/Services/Game/Mines/MineUpgradePolicy.php
app/Services/Game/Mines/AmfMineCommandGateway.php
app/Services/Game/Mines/MineTargetListService.php
app/Services/Tasks/Handlers/BuildMineHandler.php
app/Services/Tasks/Handlers/UpgradeMineHandler.php
app/Http/Controllers/Api/BuildableDepositController.php
app/Http/Controllers/Api/UpgradableMineController.php
app/Http/Resources/BuildableDepositResource.php
app/Http/Resources/UpgradableMineResource.php
```

Плюс тесты из `verification.md` §1-§7 и, возможно,
`resources/js/components/tasks/MineTargetPicker.vue` (решается в §7.1).

### Изменяемые: 14

```
storage/app/parse_zone.py                        (шаг 1: сигнатура + 6 вызовов + 2 ветки + result)
config/game.php                                  (секция buildings.mines)
app/Enums/TaskType.php                           (два case)
app/Services/TsoAmfService.php                   (два метода)
app/Providers/TaskServiceProvider.php            (три привязки + две строки реестра)
app/Http/Requests/Tasks/ScheduledTaskRequest.php (три правки: §5.3 А/Б/В)
routes/api.php                                   (два маршрута)
resources/js/views/Tasks.vue                     (6-8 точек)
resources/js/components/tasks/BuildingPicker.vue (расширение пропсами, если подходит)
lang/{en,ru,uk}/tasks.php                        (строки результатов и отказов)
lang/{en,ru,uk}/ui.php                           (подписи интерфейса)
tests/Unit/EnumsTest.php                         (два новых типа)
tests/Unit/TaskHandlerRegistryTest.php           (два новых обработчика)
tests/Feature/ApiContractTest.php                (контракт списка типов)
```

### Запрещено менять

```
app/Services/Tasks/Contracts/TaskActionHandlerInterface.php
app/Services/Tasks/TaskHandlerRegistry.php
app/Services/Tasks/Execution/*                   (шаг 8 — только чтение)
app/Services/Tasks/Handlers/*                    (существующие шесть)
app/Services/Game/ClickableBuilding*
app/Services/ZoneParserService.php
database/migrations/*                            (миграция не нужна, см. шаг 0)
```

---

# РЕВИЗИЯ 3. ОБЯЗАТЕЛЬНЫЕ ПОПРАВКИ

Этот раздел имеет приоритет над всем текстом выше. Где он противоречит §5.1 или
шагу 8 — верен этот раздел. Прочитай его ДО начала шага 1.

Основание: сверка с фактическим кодом исполнителя задач
(`app/Services/Tasks/Execution/*`), который ранее не был прочитан.

## Р3-1. КРИТИЧНО: повторная попытка может построить шахту дважды

### Что происходит

Обработчик вызывается не напрямую, а через цепочку:

```
SequenceStepExecutor -> SingleActionExecutor::executeWithRetry()
                     -> ActionRetryPolicy::execute()   // цикл до 3 попыток
                     -> handler->handle()
```

`ActionRetryPolicy` (`app/Services/Tasks/Execution/ActionRetryPolicy.php`) ловит
`GameServerErrorException` и при коде `1005` или `1012` вызывает колбэк ЗАНОВО:
`max_action_attempts = 3`, `relogin_errors = [1005]`,
`transport_retry_errors = [1012]`.

Скелет из §5.1 после отправки команды делает так:

```php
if (in_array($responseCode, self::SESSION_ERROR_CODES, true)) {
    throw new GameServerErrorException($responseCode, ...);   // <-- ОПАСНО
}
```

Код `1012` (NEWER_SESSION_DETECTED) в ответе НЕ означает, что команда не
выполнилась — сервер мог принять постройку и ответить по устаревшему соединению.
Политика перехватит исключение и вызовет `handle()` второй и третий раз.

Защитой должен был стать повторный снапшот зоны: второй проход увидел бы
`grid_occupied`. Но эта защита ненадёжна: только что отправленная постройка
сначала попадает в ОЧЕРЕДЬ стройки и на гриде здания ещё нет. Тогда
`MinePlacementPolicy` снова вернёт `allowed = true`, и команда `50` уйдёт
повторно. Итог: две шахты в очереди на один грид либо двойное списание ресурсов.
Операция необратима, ресурсы игрока не восстанавливаются.

Для `stop_production` и сбора коллектиблов повтор безвреден — идемпотентно.
Для команд `50` и `60` — нет. Поэтому образец `CollectBuildingHandler` в этой
части НЕ копируется.

### Правка (заменяет соответствующий фрагмент §5.1)

Правило: **исключение можно бросать только ДО отправки игровой команды.**
После отправки обработчик обязан вернуть строку при любом коде ответа.

```php
// ДО отправки: загрузка зоны может бросать — это безопасно, команда не ушла.
$zone = $this->zones->forAccount($account);
$decision = $this->policy->decide($zone, $grid);

// ... лог решения, проверка $decision->allowed ...

// Точка невозврата. Дальше НИ ОДНОГО throw.
$rawAmf = $this->gateway->buildMine($account, $decision->definition->buildingNumber, $grid);
$responseCode = $this->extractErrorCode($rawAmf);

if ($responseCode === 0) {
    MineTargetListService::clearCache((int) $account->id);

    return __('tasks.build_mine.built', ['name' => $decision->mineName, 'grid' => $grid]);
}

if (in_array($responseCode, self::SESSION_ERROR_CODES, true)) {
    // НЕ бросать: повтор может построить второй раз. Состояние неизвестно.
    Log::warning(sprintf(
        '[BuildMine] Account #%d: grid %d returned session code %d after the command was sent. '
        .'Outcome unknown, NOT retrying.',
        $account->id, $grid, $responseCode
    ));
    MineTargetListService::clearCache((int) $account->id);

    return __('tasks.build_mine.unknown_outcome', ['grid' => $grid]);
}

Log::warning("[BuildMine] Account #{$account->id}: grid {$grid} failed with game error {$responseCode}");

return __('tasks.build_mine.game_error', ['message' => GameErrorResolver::getMessage($responseCode)]);
```

То же самое в `UpgradeMineHandler` с ключами `tasks.upgrade_mine.*`.

Последствия для остальных разделов:

- константа `SESSION_ERROR_CODES` остаётся, но используется только для
  распознавания и логирования, а не для `throw`;
- в §5.4 добавить в три локали два новых ключа:
  `tasks.build_mine.unknown_outcome` (`:grid`) и
  `tasks.upgrade_mine.unknown_outcome` (`:grid`). Текст: результат неизвестен,
  проверьте зону вручную, повтор не выполнялся;
- `app/Services/Tasks/Execution/ActionRetryPolicy.php` НЕ менять. Он общий для
  всех задач, и его изменение сломает поведение существующих обработчиков;
- обязательный тест (добавить в `verification.md` §6): обработчик получает от
  шлюза-двойника ответ с кодом `1012`; проверяется, что исключение НЕ брошено,
  шлюз вызван РОВНО один раз, и возвращена строка `unknown_outcome`.
  Тест на `1005` — аналогично.

## Р3-2. §1.0: собрать также гриды элементов очереди

К разведке добавляется вторая цель. Кроме размеров очереди (`used` / `total`)
нужно выяснить, содержит ли элемент очереди грид и номер здания. Если содержит —
это дополнительная защита от Р3-1: политика сможет считать грид занятым, пока
постройка стоит в очереди.

Порядок действий:

1. в §1.0 выписать полный набор атрибутов элемента очереди, а не только её длину;
2. если грид у элемента есть — экспортировать список
   `build_queue.entries = [{grid, building}]` и добавить в `BuildQueueSnapshot`
   метод `hasEntryForGrid(int $grid): bool`, а в `MinePlacementPolicy` и
   `MineUpgradePolicy` — проверку этого метода с причинами
   `grid_occupied` / `upgrade_already_in_progress`;
3. если грида нет — записать это в `data-sources.md` как установленный факт и
   считать Р3-1 единственной защитой.

При пустой очереди `entries` — пустой список, а не `null`.

## Р3-3. Шаг 8 закрыт: задержка соблюдается

Проверять больше нечего, шаг 8 из плана удаляется, читать
`SequenceStepExecutor.php` не нужно. Установленные факты:

- инлайн-последовательность: `sleep($delay)` между шагами, при условии
  `$index < count($actions) - 1 && $delay > 0`;
- пошаговый режим: `executeSingleStep()` возвращает `['finished' => ..., 'nextDelay' => $delay]`;
- источник значения в обоих случаях: `(int) ($action['delay_seconds'] ?? 0)`.

Следствия:

- `delay_seconds` целочисленный, в СЕКУНДАХ. Минимальная ненулевая задержка —
  1 секунда. Требование FR-9 (не менее 1000 мс) выполняется значением
  `delay_seconds: 1` из §7.2. Дробные значения невозможны, не пытаться;
- задержка после ПОСЛЕДНЕГО шага не ставится — это нормально;
- в `decisions.md` (ADR-6) дописать одну строку: задержка обеспечивается движком,
  `SequenceStepExecutor::executeInlineSequence()` и `::executeSingleStep()`;
  `sleep` в обработчике по-прежнему запрещён.

## Р3-4. Последовательность НЕ останавливается на упавшем шаге

`executeInlineSequence()` ловит `Throwable` каждого шага, пишет
`step_results[i] = ['status' => 'failed']` и продолжает цикл. То есть при пакетной
постройке шести шахт падение второго шага не остановит отправку остальных.

Это существующее поведение движка, менять его в рамках задачи запрещено. Что
требуется:

- в §7.2 у мультивыбора вывести предупреждение: шаги независимы, сбой одного не
  отменяет остальные;
- в `requirements.md` зафиксировать это как принятое ограничение, а не как баг;
- дополнительный аргумент в пользу Р3-1: чем меньше исключений бросает
  обработчик, тем предсказуемее пакет.

## Р3-5. §4.1: подтверждённые фактические сигнатуры

Сверено с `app/Services/TsoAmfService.php`:

```
:20   public const CMD_BUILD = 50;
:22   public const CMD_UPGRADE = 60;
:145  private function buildServerAction(int $type, int $grid, int $endGrid, mixed $data = null): defaultGame_Communication_VO_dServerAction
:156  private function sendServerCall(Account $account, int $commandType, mixed $actionData, string $destination = 'SMC', string $operation = 'ExecuteServerCall', ?string $source = 'com.bluebyte.game.servlet.EventHandler', ?int $targetZoneId = null): string
:184  public function getZone(Account $account, ?int $targetZoneId = null): string
:226  public function stopProduction(Account $account, int $grid): string
:228      $action = $this->buildServerAction(0, $grid, 0, null);
:230      return $this->sendServerCall($account, self::CMD_STOP_PRODUCTION, $action);
```

Выводы:

- оба помощника ПРИВАТНЫЕ, поэтому новые методы обязаны жить внутри
  `TsoAmfService`. Вызвать их из `AmfMineCommandGateway` напрямую нельзя —
  шлюз делегирует в публичные `buildBuilding()` / `upgradeBuilding()`;
- первый параметр `buildServerAction` — это `type` внутри `dServerAction`, а не
  код команды. Для постройки туда идёт НОМЕР ЗДАНИЯ, для апгрейда — `0`.
  Код команды передаётся отдельно, вторым аргументом `sendServerCall`.
  Снимок кода в §4.1 этому соответствует, менять его не нужно;
- константы объявлены `public const` без типа. Новых констант не вводить и
  существующие не переписывать на `public const int` — это выходит за рамки задачи.

## Р3-6. Возвращаемая строка проходит через GameResponseValidator

`SingleActionExecutor::executeSingleAction()` после обработчика вызывает
`GameResponseValidator::validateAndParse($result)`. Валидатор считает строку
ответом игры, если она содержит байт `\x00`, либо начинается с `{` и содержит
`errorCode`, либо содержит `_amf_response`; в этом случае он разбирает её и
бросает `GameServerErrorException` при ненулевом `errorCode`.

Правила, которые из этого следуют:

- обработчик ОБЯЗАН возвращать локализованный человекочитаемый текст. Никогда не
  возвращать `$rawAmf` — иначе валидатор бросит исключение уже после отправки
  команды и включит повтор из Р3-1;
- строки локализации из §5.4 не должны содержать подстроки `errorCode` и
  `_amf_response`;
- в §5.4 напомнить про лимит `config('game.tasks.max_result_length')` = 150.

## Р3-7. Итог по правкам ревизии 3

| Раздел | Что меняется |
| --- | --- |
| §1.0 | + сбор атрибутов элемента очереди (Р3-2) |
| §1.3 | + возможный экспорт `build_queue.entries` |
| §5.1 | заменён блок после отправки команды (Р3-1) |
| §5.4 | + два ключа `unknown_outcome` в трёх локалях |
| §7.2 | + предупреждение о независимости шагов (Р3-4) |
| Шаг 8 | удалён, вывод перенесён в `decisions.md` (Р3-3) |
| `verification.md` §6 | + два теста на коды 1005 / 1012 без повтора |
| Запрещено менять | + `ActionRetryPolicy.php`, `SingleActionExecutor.php`, `GameResponseValidator.php` |

Число изменяемых файлов остаётся 14: шаг 8 не менял файлов, а `decisions.md` и
`verification.md` — документы спеки, а не код.
