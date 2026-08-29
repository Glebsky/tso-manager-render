# Implementation Plan: `produce_buff`

Выполнять этапы СТРОГО по порядку. Не начинать следующий этап, пока Definition of
Done предыдущего не выполнен полностью.

Гейты после КАЖДОГО этапа:

```
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build          # только если трогали resources/js
```

---

## Этап 0. Закрыть блокеры

Ничего не кодить. Закрыть три вопроса.

1. **OQ-1.** Прочитать `docs/references/client_scripts.txt` строки 307900–307940.
   Зафиксировать точное условие `if`, разделяющее два механизма каталога.
   Обновить `data-sources.md` §6.3, сняв пометку «НЕ ПРОВЕРЕНО».
   Команда: `sed -n '307890,307945p' docs/references/client_scripts.txt`
2. **Фикстура зоны.** Снять сырой AMF-ответ команды 1001 с аккаунта, у которого
   есть бафовое здание с НЕПУСТОЙ очередью. Без такой фикстуры этап 2
   невозможен.
3. **OQ-3.** Вынести оператору конфликт `constitution.md` §3 и
   `laravel-constitution.md`. Зафиксировать ответ.

**DoD:** §6.3 `data-sources.md` без пометок неуверенности; файл фикстуры лежит
в `tests/Fixtures/`; ответ оператора в OQ-3.

---

## Этап 1. Каталог и команда импорта

Зависит от: Этап 0 (OQ-1).

### Задачи

1. `app/Console/Commands/ImportProductionCatalog.php` — сигнатура из `design.md` §3.2.
   Обязательно `XMLReader`, не `simplexml_load_file` и не регексы.
2. `app/Services/Game/Production/ProductionRecipe.php`,
   `ProducerBuilding.php` — `final readonly class`.
3. `ProductionCatalogInterface.php` + `ConfigProductionCatalog.php`.
4. Запустить команду, закоммитить `config/game_production.php`.
5. Биндинг в `TaskServiceProvider`.

### Тесты

- Команда на мини-XML фикстуре (не на боевых 4.3 МБ) даёт ожидаемую структуру.
- Идемпотентность: два запуска → баит-в-байт одинаковый вывод.
- `productionTypeFor('ProvisionHouse') === 1`; `productionTypeFor('Woodcutter') === null`.
- `findRecipe(1, 'ProductivityBuffLvl3')` возвращает рецепт с 3 строками стоимости.
- `findRecipe(1, 'NoSuchRecipe') === null`.
- Рецепт без `<Costs>` даёт `costs_known === false`, а не падение.

**DoD:** 68 записей в `producers`; суммарно ≥299 рецептов; гейты зелёные.

---

## Этап 2. Расширение снапшота зоны

Зависит от: Этап 0 (фикстура).

**Самый рискованный этап.** Затрагивает код, общий для всех фич проекта.

### Задачи

1. По фикстуре из этапа 0 найти точные имена ключей очереди и атрибутов
   `productionType` / уровня здания. Записать в `data-sources.md` §8.
2. `storage/app/parse_zone.py` — расширить список атрибутов на строке 221
   и добавить извлечение очередей производства.
3. `ZoneParserService` — пробросить новые ключи в результат.
4. `ZoneSnapshot` — три метода из `design.md` §4.2 + `ProductionQueueState` DTO.

### Тесты

- **Регресс (критичен).** Старая фикстура зоны парсится как раньше; все
  существующие тесты `collect_building`, `build_mine`, `apply_buff` зелёные.
- Новая фикстура: `productionQueues()[1]->orders` содержит ожидаемые заказы.
- Фикстура без очередей: `productionQueues() === []` (НЕ `null`).
- Фикстура со сломанной/отсутствующей секцией: `productionQueues() === null`.
- `producerBuildings()` не возвращает не-производителей.

**DoD:** три различаемых состояния (`null` / `[]` / заполнено) покрыты тестами;
регресс-тест зелёный; старые ключи снапшота не тронуты (ADR-13).

---

## Этап 3. Протокольный слой

Не зависит от этапов 1–2, можно делать параллельно.

### Задачи

1. `defaultGame_Communication_VO_dTimedProductionVO.php` — ровно 5 свойств,
   `type_string` с подчёркиванием.
2. `TsoAmfService::CMD_START_TIMED_PRODUCTION = 91` и `queueTimedProduction()`.
3. `ProductionCommandGatewayInterface` + `AmfProductionCommandGateway` + биндинг.

### Тесты (обязательны по `constitution.md` §7)

- **AMF-фикстура команды 91.** Закодированное тело запроса сравнивается с
  эталонным байт-в-байт.
- Алиас в AMF — ровно `defaultGame.Communication.VO.dTimedProductionVO`.
- В теле запроса **НЕТ** `dServerAction` (AC-2).
- В теле запроса ровно 5 полей, нет `index` / `uniqueId` / `playerId` (AC-3).
- Регресс: тела запросов команд 50, 60, 107, 13002 не изменились (INV-2).

**DoD:** байт-в-байт тест команды 91 зелёный; регресс по 4 командам зелёный;
`Amf3Encoder` не изменён.

---

## Этап 4. Политика и калькулятор

Зависит от: этапы 1, 2.

### Задачи

1. `app/Enums/ProductionRejectionReason.php` — 8 значений из ADR-7.
2. `ProductionDecision.php`, `ProductionOrderPolicy.php` — порядок проверок из
   `design.md` §5, ровно в том же порядке.
3. `ProductionCost.php`, `BuffProductionCostCalculator.php`.

### Тесты

Отдельный тест на КАЖДУЮ из 7 причин отказа, возвращаемых политикой (без
`QueueFull` — его политика не выставляет), плюс:

- Приоритет: если здания нет И рецепт неизвестен → `BuildingNotFound`.
- Границы уровня: `level == min` → allow, `level == max` → allow,
  `level == min - 1` → `RecipeLevelLocked`, `level == max + 1` → `RecipeLevelLocked`.
- `upgrade_level === null` → уровневая проверка НЕ отклоняет (нет данных ≠ запрет).
- Калькулятор: `ProductivityBuffLvl3`, `amount=3`, `stacks=1` →
  `Fish 360, Bread 180, Sausage 60`, `duration 5400`.
- Калькулятор: `amount=1` → ровно табличные значения.
- `forSequence()` складывает пересекающиеся ресурсы правильно.
- `complete === false`, если хоть один рецепт без `<Costs>`.

**DoD:** политика и калькулятор покрыты без единого мока сети или БД.

---

## Этап 5. Тип задачи, хендлер, валидация

Зависит от: этапы 3, 4.

### Задачи

1. `TaskType` — добавить `ProduceBuff = 'produce_buff'`.
2. `ProduceBuffHandler` — алгоритм из `design.md` §7.
3. Регистрация в `TaskServiceProvider` (становится 9-м хендлером).
4. `ScheduledTaskRequest::produceBuffRules()` + вызов из `sequenceRules()`.
   Правила: `grid` int required; `production_type` int required min:0;
   `recipe_name` string required; `amount` int required min:1 max:99;
   `stacks` int required in:1.
5. `TaskResultPrefix` — префикс для отказов производства, если подходящего нет.

### Тесты

- Успешный путь: шлюз получает ровно ОДИН вызов с `amount=5` (AC-4).
- Отказ политики: шлюз НЕ вызывается, исключения НЕТ, возвращается
  локализованная строка (AC-7).
- Свежий снапшот: `Account::$zone_data` ни разу не читается (AC-9).
- Sequence из 2 шагов по одному аккаунту: снапшот запрашивается снова после
  первого успешного заказа (ADR-10).
- Технический сбой (невалидный AMF) → исключение и retry.
- Валидация: `amount = 0` и `amount = 100` отклоняются.

**DoD:** все 8 причин отказа дают `status: completed` с объяснением; нет пути,
где отказ политики становится исключением.

---

## Этап 6. API-эндпоинт

Зависит от: этапы 1, 2.

### Задачи

1. `BuffProducerListService` — сборка ответа, включая `shares_queue_with`
   (группировка зданий по `production_type`) и `availability` по ADR-8.
2. `BuffProducerController` (только вызов сервиса), `BuffProducerResource`.
3. Маршрут в `routes/api.php` в той же группе middleware, что соседние
   account-эндпоинты.

### Тесты

- Здание без `productionType` в ответ не попадает.
- Два здания с одинаковым `production_type` → у каждого в `shares_queue_with`
  указан grid другого (AC-11).
- Рецепт вне диапазона уровня в список НЕ попадает (FR-6.1).
- Рецепт с `requiresEvent` ПОПАДАЕТ со `availability: "unknown"` (ADR-8).
- `productionQueues() === null` → `meta.queue_data_available === false`, ответ 200.
- Авторизация: чужой аккаунт → 403.

**DoD:** контроллер — только делегация; логики в контроллере нет.

---

## Этап 7. UI

Зависит от: этапы 5, 6.

### Задачи

1. Новые ref и модалка выбора здания-производителя (переиспользовать паттерн
   `showBuildingModal`).
2. Список рецептов с чекбоксами и полем количества на каждый рецепт.
3. Сводка стоимости по всей sequence.
4. Предупреждение о разделяемой очереди (FR-11) — обязательно, это главное
   расхождение с ожиданиями оператора.
5. Ветка в `addStepToSequence()` из `design.md` §10.2.
6. Рендер шага в списке + иконка + лейбл.

**DoD:** `npm run build` без ошибок; ручная проверка — выбор 2 рецептов с кол-вом
3 и 5 даёт 2 шага с `amount` 3 и 5 (не 8 шагов).

---

## Этап 8. Локализация

Зависит от: этапы 5, 7.

1. Добавить 18 ключей из `design.md` §11 во все три локали: `en`, `ru`, `uk`.
2. `php artisan tso:lang:export-frontend`.

**DoD:** нет ни одного хардкодного текста в новом коде; все три локали имеют
одинаковый набор ключей.

---

## Этап 9. Финальная верификация

Пройти весь `verification.md` сверху вниз. Проверить AC-1..AC-12 поштучно.

**DoD:** все четыре гейта зелёные; каждый AC имеет ссылку на конкретный тест
или отметку о ручной проверке.

---

## Порядок зависимостей

```
0 ─┬→ 1 ─┬→ 4 ─→ 5 ─┬→ 7 ─→ 8 ─→ 9
   └→ 2 ─┴→ 6 ───────┘
        3 ────→ 5
```

Этап 3 можно делать параллельно с 1 и 2.
Этап 6 можно делать параллельно с 4 и 5.

---

## Этап 0.4 (НОВЫЙ, обязательный) — положить AMF-дампы в `docs/references/`

Прямое требование оператора: дампы живого трафика — общий ресурс проекта,
а не приложение к одному спеку. Они должны быть доступны любой будущей
задаче и любому агенту.

### Целевая структура

```
docs/references/amf/
  README.md                                  таблица пар, грабли, инструкция по съёму дампов
  req.txt                                    исходник от оператора как есть
  amf3.py                                    декодер AMF3 без зависимостей
  decode.py                                  CLI: --schema, --path
  start_timed_production_91_response.b64     ответ на команду 91
  amf/call01..call08_*.{bin,b64,json,txt}     8 пар запрос/ответ
  amf/call03_response.decoded.json.gz         снапшот зоны в JSON (сжат)
  amf/call03_response.schema.json             схема dZoneVO
```

### Задачи

1. Скопировать содержимое `references/` из архива спека в `docs/references/amf/`.
2. Проверить `.gitattributes`: `*.bin`, `*.gz` — `binary`; не прогонять через
   конвертацию переводов строк (иначе фикстуры сломаются, ср. P-13 про CRLF).
3. Добавить ссылку на `docs/references/amf/README.md` в корневой `AGENTS.md` и
   `docs/spec/constitution.md` — в раздел об источниках истины по протоколу.
   Приоритет источников зафиксировать явно:
   живой трафик > `client_scripts.txt` > XML-каталоги > userscripts.
4. Симлинки/копии для тестов: `tests/Fixtures/Amf/zone_snapshot_1002.bin`
   ← `docs/references/amf/amf/call03_response.bin`,
   `tests/Fixtures/Amf/start_timed_production_91_response.bin` ← декод из b64.
   Не дублировать 595 КБ дважды без нужды — лучше читать из `docs/references`
   через хелпер в тестах.
5. В `docs/foundbugs.md` завести запись по P-23: проверить, что
   `ZoneParserService.php` и `storage/app/parse_zone.py` читают вложенное значение
   externalizable `ArrayCollection`. Если нет — это существующий баг, не связанный
   с фичей, и он молча портит чтение зоны.

### Критерий готовности

`python3 docs/references/amf/decode.py docs/references/amf/amf/call03_response.bin \
  --path body.data.data.timedProductions_vector.3` печатает заказ `Tome`
(`productionType = 2`, `buildingGrid = 0`).

### Зависимости

Нет. Выполняется первым шагом, так как этапы 2 (парсер снапшота) и 7
(тесты) опираются на эти фикстуры.

### Статус этапа 0 после разбора `req.txt`

| Пункт | Было | Стало |
| --- | --- | --- |
| Формат `dTimedProductionVO` в команде 91 | гипотеза | ЗАКРЫТО трафиком (14 полей) |
| Ключи очередей в снапшоте зоны | блокер | ЗАКРЫТО (`timedProductions_vector`) |
| Очередь общая по `productionType` | вывод из клиента | ЗАКРЫТО (`buildingGrid = 0`) |
| Запрос команды 91 байт-в-байт | открыт (OQ-4) | ОСТАЁТСЯ ОТКРЫТ — нужен дамп по инструкции из README |
