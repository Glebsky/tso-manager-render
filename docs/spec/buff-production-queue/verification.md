# Verification: `produce_buff`

---

## 1. Гейты

Все четыре обязательны и должны быть зелёными после каждого этапа:

```
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build
```

Запрещено: `--dirty`, `--filter` в финальной проверке, пропуск phpstan
«потому что ошибки в чужом коде» (тогда зафиксировать baseline до начала
работы и сравнивать с ним).

---

## 2. Обязательная AMF-фикстура (`constitution.md` §7)

Самый важный тест во всей фиче.

**Файл:** `tests/Unit/Amf/StartTimedProductionEncodingTest.php`

**Сценарий:**

1. Собрать запрос через `TsoAmfService::queueTimedProduction()` с фиксированными
   аргументами: `grid=1234`, `productionType=1`,
   `typeString='ProductivityBuffLvl3'`, `amount=3`, `stacks=1`.
2. Сравнить закодированное тело байт-в-байт с эталоном из
   `tests/Fixtures/Amf/start_timed_production_91.bin`.

**Дополнительные ассерты по тому же телу:**

| Ассерт | Покрывает |
| --- | --- |
| Содержит алиас `defaultGame.Communication.VO.dTimedProductionVO` | AC-1 |
| НЕ содержит подстроки `dServerAction` | AC-2, P-2 |
| Ровно 5 свойств; нет `index`, `uniqueId`, `playerId`, `producedItems`, `collectedTime` | AC-3, P-10 |
| Содержит `type_string` (не `typeString`) | P-9 |
| Номер команды в запросе равен 91, не 107 | P-1 |
| `stacks` закодирован как `1`, не `null` | P-11 |

**Регресс (INV-2).** Существующие тесты кодирования команд 50, 60, 107, 13002
остаются зелёными без правки фикстур. Если пришлось править фикстуру —
это регрессия, а не обновление.

---

## 3. Соответствие AC → тест

| AC | Формулировка (сокр.) | Где проверяется | Тип |
| --- | --- | --- | --- |
| AC-1 | Команда 91 с алиасом `dTimedProductionVO` | `StartTimedProductionEncodingTest` | unit |
| AC-2 | Нет обёртки `dServerAction` | `StartTimedProductionEncodingTest` | unit |
| AC-3 | Ровно 5 полей в VO | `StartTimedProductionEncodingTest` | unit |
| AC-4 | `amount=5` → ОДИН вызов шлюза | `ProduceBuffHandlerTest` | unit |
| AC-5 | Стоимость = Сумма × amount × stacks | `BuffProductionCostCalculatorTest` | unit |
| AC-6 | Сводная стоимость по sequence в UI | ручная проверка №3 | manual |
| AC-7 | Отказ → completed с причиной, не исключение | `ProduceBuffHandlerTest` (8 кейсов) | unit |
| AC-8 | Фильтр рецептов по уровню и group | `ProductionOrderPolicyTest`, `BuffProducerEndpointTest` | unit + feature |
| AC-9 | Свежий снапшот, не `zone_data` | `ProduceBuffHandlerTest` | unit |
| AC-10 | Каталог генерируется идемпотентно | `ImportProductionCatalogTest` | feature |
| AC-11 | Разделяемая очередь показана оператору | `BuffProducerEndpointTest` + ручная №4 | feature + manual |
| AC-12 | Старый снапшот не ломается | `ZoneParserRegressionTest` | unit |

**Правило:** ни один AC не может остаться без строки в этой таблице.
«Проверено визуально» для бэкенд-AC НЕ принимается.

---

## 4. Полный тест-план

### 4.1. `ImportProductionCatalogTest` (feature)

- На мини-фикстурах XML даёт ожидаемый массив.
- Два запуска → идентичный вывод (AC-10, P-17).
- Здание с `productionType="-1"` не попадает в `producers` (P-3).
- Здание с `productionType="0"` ПОПАДАЕТ (граничный случай, P-3).
- Пустой `<TimedProductionList id="1">` НЕ даёт пустого списка рецептов для
  типа 1 — подключается динамический пул бафов (P-7).
- `<Buff produceable="true">` без `<Costs>` → `costs_known: false` (P-8).

### 4.2. `ConfigProductionCatalogTest` (unit)

- `productionTypeFor` — известное и неизвестное имя.
- `recipesFor` для несуществующего типа → `[]`, не исключение.
- `recipesFor(3)` → `[]` (отсутствующее значение типа, P-19).
- `findRecipe` — найдено / `null`.

### 4.3. `ZoneParserProductionTest` + `ZoneParserRegressionTest` (unit)

| Кейс | Ожидание |
| --- | --- |
| Фикстура с непустой очередью типа 1 | `productionQueues()[1]->orders` содержит заказы |
| Фикстура без очередей | `productionQueues() === []` |
| Фикстура без секции очередей | `productionQueues() === null` |
| Старая фикстура (до расширения) | все старые ключи на месте, новые = null (AC-12) |
| Здание без `productionType` | не в `producerBuildings()` |

**Критично:** три состояния `null` / `[]` / заполнено должны быть
различимы. Слияние `null` и `[]` — нарушение INV-4.

### 4.4. `ProductionOrderPolicyTest` (unit)

По одному тесту на каждую причину:

1. `BuildingNotFound` — grid нет в снапшоте.
2. `NotAProducer` — `production_type === null`.
3. `ProductionTypeMismatch` — payload 1, снапшот 2.
4. `BuildingUpgrading`.
5. `RecipeUnknown`.
6. `RecipeLevelLocked` × 2 (ниже min, выше max).
7. `QueueDataUnavailable` — `productionQueues() === null`.

Плюс:

- Границы: `level == min` → allow; `level == max` → allow.
- `upgrade_level === null` → уровневая проверка не отклоняет.
- Приоритет причин: здания нет И рецепт неизвестен → `BuildingNotFound`.
- Политика НИКОГДА не возвращает `QueueFull` (OQ-2, P-18).
- Политика не делает ни одного сетевого или БД-вызова (нет моков вообще).

### 4.5. `BuffProductionCostCalculatorTest` (unit)

| Вход | Ожидание |
| --- | --- |
| `ProductivityBuffLvl1`, amount 1 | `Fish 10`, duration 60 |
| `ProductivityBuffLvl3`, amount 1 | `Fish 120, Bread 60, Sausage 20`, duration 1800 |
| `ProductivityBuffLvl3`, amount 3 | `Fish 360, Bread 180, Sausage 60`, duration 5400 |
| `forSequence` двух рецептов с общим `Fish` | ресурс суммирован |
| Любое слагаемое с `costs_known: false` | `complete === false` |
| `amount = 1, stacks = 1` | точно табличные значения, без float |

Ассерт типов: все значения ресурсов — `int`, не `float`.

### 4.6. `ProduceBuffHandlerTest` (unit)

- Успешный путь: шлюз получил ровно 1 вызов с ожидаемыми аргументами,
  включая `amount = 5` (AC-4, P-21).
- Каждый из 8 отказов → шлюз НЕ вызван, исключений нет, результат —
  локализованная строка с правильным префиксом (AC-7).
- `Account::$zone_data` не читается ни в одном пути (AC-9). Проверка — мок
  провайдера обязательно вызван.
- Sequence из 2 шагов → провайдер снапшота вызван 2 раза (P-12).
- Невалидный AMF-ответ → исключение (технический сбой ИСКЛЮЧЕН из ADR-7).
- `supports()` истина только для `'produce_buff'`.

### 4.7. `ScheduledTaskRequestProduceBuffTest` (feature)

| Вход | Ожидание |
| --- | --- |
| Корректный шаг | 201/200 |
| `amount = 0` | 422 |
| `amount = 100` | 422 |
| `stacks = 2` | 422 (ADR-9) |
| Нет `recipe_name` | 422 |
| `production_type = -1` | 422 |
| Существующие типы шагов (`apply_buff` и др.) | валидируются как раньше (INV-1) |

### 4.8. `BuffProducerEndpointTest` (feature)

- Здание без `productionType` отсутствует в ответе.
- Два здания одного типа → взаимные `shares_queue_with` (AC-11, P-5).
- Одно здание типа → `shares_queue_with === []`.
- Рецепт вне диапазона уровня отсутствует (AC-8).
- Рецепт с `requiresEvent` присутствует со `availability: "unknown"` (ADR-8).
- `productionQueues() === null` → 200 и `meta.queue_data_available === false`.
- Чужой аккаунт → 403. Неавторизованный → 401.

---

## 5. Ручные проверки

Выполнять после этапа 8, на реальном аккаунте, фиксируя результат.

**№1. Создание шага.** Выбрать бафовое здание, один рецепт, количество 2.
Ожидание: 1 шаг в sequence, в payload `amount: 2`.

**№2. Мультивыбор рецептов.** Два рецепта, количества 3 и 5.
Ожидание: ровно **2** шага с `amount` 3 и 5. НЕ 8 шагов (P-21).

**№3. Сводка стоимости (AC-6).** В sequence с двумя шагами сверить сумму
вручную с таблицей стоимостей из `data-sources.md`.

**№4. Предупреждение о разделяемой очереди (AC-11, FR-11).**
Аккаунт с двумя зданиями одного типа. Ожидание: явное предупреждение, что
очередь общая. **Это главное расхождение с ожиданиями оператора** — если
предупреждения нет, фича НЕ готова.

**№5. Исполнение в игре.** Запустить таску. Открыть игру, проверить, что в
очереди здания появился заказ с верным рецептом и верным количеством.
Отдельно проверить, что ОЧЕРЕДЬ СТРОИТЕЛЬСТВА НЕ ЗАТРОНУТА (ADR-1).

**№6. Переполнение очереди.** Забить очередь вручную в игре, затем запустить
таску. Ожидание: шаг `completed` с понятной причиной, таска не упала,
остальные шаги выполнились (ADR-7). Зафиксировать код ошибки игры — это
данные для закрытия OQ-2.

**№7. Удалённое здание.** Запланировать шаг, снести здание в игре, выполнить
таску. Ожидание: `BuildingNotFound`, шаг `completed`, без исключения.

**№8. Три локали.** Переключить язык на `en`, `ru`, `uk`. Ожидание: ни одного
ключа вида `tasks.production.*` в сыром виде на экране.

---

## 6. Архитектурные проверки (ревью-чеклист)

Каждый пункт проверяется grep'ом или глазами перед мержем.

- [ ] `grep -r 'mBuildQueue' app/Services/Game/Production app/Services/Tasks/Handlers/ProduceBuffHandler.php`
      → пусто (ADR-1).
- [ ] `grep -rn 'zone_data' app/Services/Tasks/Handlers/ProduceBuffHandler.php`
      → пусто (ADR-10, AC-9).
- [ ] `grep -rn 'productionType === 1\|production_type == 1'` в бэкенде → пусто
      (ADR-11).
- [ ] `git diff --stat app/Services/Amf/Amf3Encoder.php` → пусто (P-2, §2.4
      `data-sources.md`).
- [ ] `git diff --stat` по `startProduction`/`stopProduction`/`StartProductionHandler`
      → пусто (P-1).
- [ ] В `ProductionOrderPolicy` нет ни одного `use` фасада, HTTP-клиента
      или Eloquent-модели, кроме DTO.
- [ ] `config/game_production.php` содержит предупреждение «НЕ
      РЕДАКТИРОВАТЬ ВРУЧНУЮ» и закоммичен.
- [ ] Контроллер `BuffProducerController` — меньше 20 строк, только делегация.
- [ ] В `Tasks.vue` нет хардкодных русских/английских строк в новом коде.
- [ ] Все три локали содержат одинаковый набор 18 ключей.
- [ ] В спеке нет ни одного открытого OQ (OQ-1 и OQ-2 закрыты или переведены
      в follow-up с зафиксированным временным поведением).

---

## 7. Критерии неготовности (stop-ship)

Фича НЕ готова к мержу, если верно хотя бы одно:

1. Нет байт-в-байт AMF-теста на команду 91.
2. Отказ политики где-то выражается исключением или `status: failed`.
3. UI не предупреждает о разделяемой очереди.
4. `amount` разворачивается в N шагов или N вызовов шлюза.
5. Каталог написан вручную или парсится в рантайме.
6. Любой из четырёх гейтов красный.
7. Старая фикстура зоны перестала парситься (AC-12).
8. Стоимость показывается как `0` для рецепта без `<Costs>` вместо пометки
   «неизвестна».
