# Verification: build_mine / upgrade_mine

Файлы тестов — в `tests/Unit/Game/Mines/` и `tests/Feature/Tasks/`.
Имена тестов ниже обязательны: они соотносятся с AC и INV из `requirements.md`.

## 1. `ConfigMineCatalogTest` (unit)

| Тест | Ожидание |
| --- | --- |
| `it resolves every mine from config by deposit name` | шесть записей из FR-7 возвращают правильные `buildingNumber` |
| `it resolves by building name` | `IronMine` → `50` |
| `it returns null for unknown deposit` | `Stone` → `null` |
| `it is case sensitive` | `ironmine` → `null` (pitfall 8) |
| `it exposes exactly six definitions` | `count(all()) === 6` (INV-8) |

Отдельный тест против реального конфига (не фикстуры):
`it keeps the shipped mine numbers` — жёстко сверяет шесть пар с таблицей FR-7.
Это защита от случайной правки `config/game.php`.

## 2. `BuildQueueBudgetTest` (unit)

| Тест | Ожидание |
| --- | --- |
| `it reports free slots` | `used=2, total=5` → `3` |
| `it clamps negative budget to zero` | `used=6, total=5` → `0` |
| `it treats missing queue data as no free slots` | `null` → `0`, `hasFreeSlot() === false` (ADR-7) |

## 3. `MinePlacementPolicyTest` (unit)

Каждый кейс — отдельный тест, проверяется и `allowed`, и `reason`:

| Сценарий | `allowed` | `reason` | Связь |
| --- | --- | --- | --- |
| залежь `IronOre`, грид пуст, слот есть | `true` | `ok` | AC-1 |
| на гриде нет залежи | `false` | `no_deposit_at_grid` | FR-4.3 |
| залежь `Stone` (вне каталога) | `false` | `unknown_deposit_type` | AC-4 |
| `amount = 0` | `false` | `deposit_empty` | FR-4.5 |
| на гриде уже есть здание | `false` | `grid_occupied` | AC-3, INV-6 |
| очередь заполнена | `false` | `build_queue_full` | AC-5 |
| очередь неизвестна (`null`) | `false` | `build_queue_full` | ADR-7 |

Дополнительно фиксируется приоритет причин:
`it reports occupied grid before queue limits` — занятый грид плюс полная очередь дают
`grid_occupied` (чтобы сообщение было информативным для игрока).

Критический тест безопасности:
`it never returns a definition when rejected for unknown deposit` — при отказе по
`unknown_deposit_type` поле `definition` равно `null`, то есть отправить команду
технически невозможно.

## 4. `MineUpgradePolicyTest` (unit)

| Сценарий | `allowed` | `reason` | Связь |
| --- | --- | --- | --- |
| `IronMine` уровень 3, производство идёт, слот есть | `true` | `ok` | AC-2 |
| на гриде нет здания | `false` | `no_building_at_grid` | FR-5.2 |
| на гриде `Woodcutter` | `false` | `not_a_mine` | AC-6, ADR-11 |
| уровень 7 из 7 | `false` | `max_level_reached` | AC-7 |
| уровень 4, `max_level = 4` в payload | `false` | `max_level_reached` | FR-5.4 |
| уровень 3, `max_level = 9` в payload | `true` | `ok` (цель урезана до 7) | FR-5.4 |
| апгрейд уже идёт | `false` | `upgrade_already_in_progress` | FR-5.5 |
| производство остановлено | `false` | `production_inactive` | FR-5 |
| очередь заполнена | `false` | `build_queue_full` | AC-5 |

## 5. `AmfMineCommandGatewayTest` (unit с фейком транспорта)

Это самые важные тесты всего набора: они фиксируют протокол (INV-2).

| Тест | Проверка |
| --- | --- |
| `it sends command 50 with building number in the type field` | `commandType === 50`, `action.type === 50` для `IronMine`, `action.grid === <grid>`, `action.endGrid === 0`, `action.data === null` |
| `it sends command 60 with zero type` | `commandType === 60`, `action.type === 0`, `action.grid === <grid>` |
| `it sends exactly one call per invocation` | транспорт вызван ровно один раз |

При правке этих тестов в будущем сначала обновляется `data-sources.md` с
доказательством из `client_scripts.txt`, и только потом код.

## 6. Feature-тесты обработчиков

`BuildMineHandlerTest`:

| Тест | Ожидание |
| --- | --- |
| `it builds a mine on a discovered deposit` | шлюз вызван с номером `50` и нужным гридом; результат содержит имя шахты |
| `it does not call the gateway when the grid is occupied` | **шлюз не вызывался ни разу** (AC-3) |
| `it does not call the gateway for unknown deposit type` | шлюз не вызывался (AC-4) |
| `it does not call the gateway when the build queue is full` | шлюз не вызывался (AC-5) |
| `it throws on missing grid` | `InvalidTaskTypeException` (FR-11) |
| `it rethrows session errors` | коды `1005` и `1012` → исключение (AC-8) |
| `it returns a message for other game errors` | любой другой код → строка, без исключения (AC-8) |
| `it supports only its own task type` | `supports('build_mine') === true`, `supports('collect_building') === false` |

`UpgradeMineHandlerTest` — зеркальный набор с командой `60`, плюс:
`it does not call the gateway for a non mine building` (AC-6) и
`it does not call the gateway at max level` (AC-7).

`TaskHandlerRegistryTest` (дополнить существующий):
`it resolves the new mine handlers` — для `build_mine` и `upgrade_mine` реестр возвращает
ожидаемые классы, а для старых типов — те же, что и раньше (INV-5).

## 7. Валидация и API

`ScheduledTaskRequestTest` (дополнить):

| Тест | Ожидание |
| --- | --- |
| `it rejects build_mine without grid` | `422`, ошибка на `payload.grid` (AC-9) |
| `it rejects upgrade_mine with max_level above seven` | `422` (AC-9) |
| `it rejects upgrade_mine with max_level zero` | `422` |
| `it accepts a valid build_mine payload` | `201`/`200` |
| `it validates mine steps inside a sequence` | шаг `build_mine` без `grid` даёт `422` |

`BuildableDepositEndpointTest` и `UpgradableMineEndpointTest`:

| Тест | Ожидание |
| --- | --- |
| `it requires authentication` | `401` без токена |
| `it returns deposits with reasons` | структура ответа из FR-13 |
| `it returns an empty list when the zone fails to load` | `200`, `data === []` (FR-13) |
| `it never sends game commands` | шлюз команд не вызывался (INV-7) |
| `it agrees with the handler policy` | для одного и того же снапшота `buildable` совпадает с `decision->allowed` (INV-4) |

## 8. Парсер зоны

Проверка выполняется вручную на сохранённом дампе зоны:

```bash
python storage/app/parse_zone.py <дамп> > zone.json
```

Чек-лист (AC-10):

- [ ] `deposits` непустой, у каждой записи есть `grid`, `name`, `amount`, `max_amount`;
- [ ] имена руд совпадают с ключами `config('game.buildings.mines')` буквально;
- [ ] `build_queue.used` и `build_queue.total` — целые числа, `total >= used`;
- [ ] все старые ключи присутствуют и не изменили формат (INV-5);
- [ ] если в зоне есть шахта, её грид не появляется как свободная залежь в API-списке.

## 9. Ручная проверка на тестовом аккаунте

Команды `50` и `60` необратимы и тратят реальные ресурсы. Использовать только
тестовый аккаунт.

Порядок:

1. Открыть планировщик задач, выбрать «Построить шахту».
   - [ ] в списке видны только разведанные залежи шести типов;
   - [ ] залежи с уже построенной шахтой помечены недоступными с причиной.
2. Запланировать одну постройку, выполнить: `php artisan tso:execute-tasks --task=<ID>`.
   - [ ] в игре появилась стройка на нужном гриде и нужного типа;
   - [ ] занят ровно один слот очереди;
   - [ ] строка результата содержит имя шахты и грид, не обрезана.
3. Запустить ту же задачу второй раз.
   - [ ] результат — пропуск «грид занят», второй стройки нет (INV-6).
4. Запланировать апгрейд этой же шахты после завершения стройки.
   - [ ] уровень вырос на единицу;
   - [ ] повторный запуск во время идущего апгрейда даёт пропуск, а не вторую команду.
5. Проверить мультивыбор из трёх залежей.
   - [ ] создалась последовательность из трёх шагов с задержкой не менее 1 с (AC-11);
   - [ ] в логах интервал между отправками не менее 1000 мс (FR-9).
6. Проверить поведение при полной очереди стройки.
   - [ ] задача возвращает пропуск, в игру ничего не отправлено (AC-5).
7. Проверить логи.
   - [ ] есть запись решения для каждого запуска, включая отказы (FR-12);
   - [ ] в записи есть `account_id`, `grid`, имя, номер, свободные слоты, `reason`.

## 10. Гейты качества

```bash
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
```

Дополнительно (AC-12):

- [ ] `phpstan-baseline.neon` не изменён;
- [ ] `composer.json` и `package.json` не изменены;
- [ ] `.env` не изменён;
- [ ] ключи `lang` совпадают в `en`, `ru`, `uk`;
- [ ] для каждого случая двух enum причин существует строка локализации.

## 11. Финальная таблица трассировки

| Критерий | Где проверяется |
| --- | --- |
| AC-1 | §5, §6 (`it builds a mine on a discovered deposit`) |
| AC-2 | §5, §6 (`UpgradeMineHandlerTest`) |
| AC-3 | §3, §6 (`it does not call the gateway when the grid is occupied`) |
| AC-4 | §3, §6 |
| AC-5 | §2, §3, §4, §6, §9.6 |
| AC-6 | §4, §6 |
| AC-7 | §4, §6 |
| AC-8 | §6 |
| AC-9 | §7 |
| AC-10 | §8 |
| AC-11 | §9.5 |
| AC-12 | §10 |
| INV-1 | §3 (`it never returns a definition when rejected`) |
| INV-2 | §5 |
| INV-3 | §2, §3, §4 |
| INV-4 | §7 (`it agrees with the handler policy`) |
| INV-5 | §6 (`TaskHandlerRegistryTest`), §8 |
| INV-6 | §9.3 |
| INV-7 | §7 (`it never sends game commands`) |
| INV-8 | §1 (`it exposes exactly six definitions`) |
