# Implementation Plan: market-tradeables

Правила работы:

1. Этапы выполняются СТРОГО по порядку.
2. После каждого этапа прогонять гейты из `verification.md` §1.
3. Не начинать следующий этап, пока гейты красные.
4. Один этап = один коммит.
5. Если реальность не совпадает со спекой — ОСТАНОВИСЬ и спроси. Не
   импровизируй протокол.

---

## Этап 0. Фикстура живого трафика (мягкий блокер)

**Задачи**

1. Проверить наличие в `docs/references/amf/` дампа `GetAvailableOffers`,
   содержащего лот с `type != 0`.
2. Если его НЕТ — зафиксировать это в PR-описании и продолжать по коду
   клиента (`data-sources.md` §2). Запросить у оператора дамп в фоновом
   режиме; не блокировать работу.
3. Создать синтетические фикстуры в `tests/Fixtures/Market/` для AC-1..AC-6
   ровно в том виде, в каком их отдаёт `parse_market.py` (поля `id`, `senderID`,
   `senderName`, `type`, `slotType`, `created`, `offer`, `lotsRemaining`).

**Definition of Done**

- Фикстуры лежат в репозитории и покрывают все четыре значения `type`,
  случай `@`, случаи бафа из 2, 3 и 4 полей.

---

## Этап 1. Домен декодирования (без БД и сети)

**Задачи**

1. `app/Enums/MarketItemKind.php` — по `design.md` §2.1.
2. `app/Services/Market/Tradeables/TradeSide.php` — §2.2.
3. `app/Services/Market/Tradeables/DecodedTradeOffer.php` — §2.3.
4. `app/Services/Market/Tradeables/TradeOfferDecoder.php` — §2.4.
5. `app/Services/Market/Tradeables/TradeableIdFactory.php` — §2.5.
6. Унит-тесты: `tests/Unit/Market/TradeOfferDecoderTest.php`,
   `tests/Unit/Market/TradeableIdFactoryTest.php`.

**Definition of Done**

- Все примеры строк из `requirements.md` AC-1..AC-6 декодируются как ожидается.
- `decode()` возвращает `null` (а не бросает) на: 2 сегментах, 4 сегментах,
  `type = 7`, пустой строке, нечисловом количестве ресурса.
- `TradeableIdFactory::parse(fromSide($side))` даёт исходные kind/base/subject
  (round-trip тест) для всех четырёх видов.
- Сеть, БД и файлы в этих классах НЕ используются.

---

## Этап 2. Резолвер имён

**Задачи**

1. `CompositeTradeableNameResolver` по `design.md` §2.6.
2. Перебинд в `app/Providers/MarketServiceProvider.php:34`.
3. Добавить `'ADN'` в `LangFrontendExportCommand::FRONTEND_GAME_SECTIONS`.
4. Выполнить `php artisan tso:lang:export-frontend` и закоммитить результат.
5. Тест `tests/Unit/Market/CompositeTradeableNameResolverTest.php`.

**Definition of Done**

- `resolve('adventure:MadHenry', '')` на локали `ru` возвращает `Дикая Мери`.
- `resolve('adventure:WitchOfTheSwamp', '')` → `Болотная ведьма`.
- `resolve('Marble', '')` даёт тот же результат, что и старый
  `GameResourceNameResolver` (тест сравнивает две реализации напрямую).
- `resolve('buff:FillDeposit', '')` использует ключ `FillDepositAny` (P-10).
- Неизвестный id не бросает исключение и не возвращает композитный id.
- `resources/js/lang/generated/ru.json` содержит секцию `ADN`.

---

## Этап 3. Схема БД

**Задачи**

1. Миграция `2026_09_02_120000_add_tradeable_kind_to_market_tables.php` по
   `design.md` §3 (с работающим `down()`).
2. Обновить `$fillable`/`$casts` в `app/Models/MarketOffer.php` и
   `app/Models/MarketHistory.php`.

**Definition of Done**

- `php artisan migrate` и `php artisan migrate:rollback --step=1` проходят без
  ошибок на пустой и на заполненной базе.
- `unique(['server_id','offer_id'])` не изменён (P-19); проверить выводом
  индексов таблицы до и после.
- Существующие строки получили `item_kind = 'resource'` (INV-6).

---

## Этап 4. Парсер и запись

**Задачи**

1. Переписать `MarketOfferParser` по `design.md` §2.7.
2. Добавить новые колонки в `MarketOfferPersister`: и в массив вставки,
   И в `$updateColumns` (P-18), и в insert истории (без `sender_name` и
   `lots_remaining`, P-20).
3. Пробросить счётчик `unparsed_offers` в `MarketSyncLogger`.
4. Тесты: `tests/Unit/Market/MarketOfferParserTest.php` +
   `tests/Feature/Market/MarketSyncTradeablesTest.php`.

**Definition of Done**

- AC-1..AC-6, AC-9 зелёные.
- Golden-тест INV-2: ресурсные лоты дают побайтово те же значения, что до
  изменений (ожидаемые значения зафиксированы в тесте как литералы).
- Повторный синк тех же данных обновляет `item_kind` у существующей строки
  (тест на P-18).

---

## Этап 5. Сервисы чтения и API

**Задачи**

1. `MarketOfferResource` — новые поля (§5.1), контракт `using()` сохранён.
2. `MarketCatalogService` и `PopularItemService` — фильтр `?MarketItemKind $kind`.
3. `whereNotNull('price')` в `MarketAnalyticsService`, `MarketHistoryAggregator`,
   `Arbitrage/LoopArbitrageFinder` (P-21).
4. Контроллеры: валидация `kind` (§5.2), проброс в сервисы.
5. `MarketCacheService` — `kind` в ключах (P-22).
6. Feature-тесты на эндпоинты, включая публичный prefix `public/market`.

**Definition of Done**

- AC-7, AC-8, AC-11 зелёные.
- `kind=мусор` даёт 422, не 500.
- Запрос без `kind` возвращает ровно тот же набор, что и до изменений
  (обратная совместимость, ADR-11).
- Два запроса с разным `kind` не пересекаются в кэше (тест).

---

## Этап 6. Фронтенд

**Задачи**

1. `resources/js/lang/gameNames.js` — `parseTradeableId` и обновлённый
   `marketItemName` (§7).
2. `resources/js/services/api/market.js` — параметр `kind`.
3. `MarketDataTable.vue` — бейдж вида; `MarketAnalytics.vue` и
   `PublicMarketAnalytics.vue` — фильтр вида.

**Definition of Done**

- `npm run build` зелёный.
- Ни в одном состоянии UI не показывается текст вида `adventure:MadHenry`
  или `buff:FillDeposit:Fish`.
- Переключение языка меняет имена приключений без запроса к API.

---

## Этап 7. Каталог и чистка

**Задачи**

1. `ImportTradeablesCatalog` + `config/game_tradeables.php` (§8).
2. `CleanupLegacyTradeablesCommand` с `--dry-run` (FR-12, ADR-9).
3. Запустить `--dry-run`, показать сводку оператору, и только потом —
   без флага.
4. Документировать обе команды в `docs/` рядом с остальными artisan-командами
   рынка.

**Definition of Done**

- AC-10 зелёный (идемпотентный вывод).
- Каталог содержит 189 приключений (79 торгуемых) и 2916 бафов
  (363 торгуемых) — числа проверяются тестом по `data-sources.md` §5.
- `--dry-run` ничего не удаляет (тест сравнивает count до/после).

---

## Порядок выкатки на прод

1. `php artisan migrate` (этап 3 безопасен, только добавление).
2. Деплоить код.
3. `php artisan tso:lang:export-frontend` (если не закоммичено).
4. Дождаться одного цикла синка, проверить появление строк с
   `item_kind <> 'resource'`.
5. `php artisan market:cleanup-legacy-tradeables --dry-run`, затем без флага.
6. Сбросить кэш рынка (`php artisan cache:clear` или точечные ключи
   `MarketCacheService`).
