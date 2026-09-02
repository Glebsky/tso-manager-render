# Design: market-tradeables

## 1. Карта изменений

### 1.1. Новые файлы

| Файл | Назначение |
| --- | --- |
| `app/Enums/MarketItemKind.php` | string enum вида сущности |
| `app/Services/Market/Tradeables/TradeSide.php` | одна сторона лота (DTO) |
| `app/Services/Market/Tradeables/DecodedTradeOffer.php` | результат декодирования строки |
| `app/Services/Market/Tradeables/TradeOfferDecoder.php` | декодер строки `offer` |
| `app/Services/Market/Tradeables/TradeableIdFactory.php` | единственное место сборки/разбора `item_id` |
| `app/Services/Market/Tradeables/CompositeTradeableNameResolver.php` | резолвер имён по секциям ADN/BUI/RES |
| `app/Console/Commands/ImportTradeablesCatalog.php` | генерация `config/game_tradeables.php` из XML |
| `app/Console/Commands/CleanupLegacyTradeablesCommand.php` | удаление испорченных старых строк |
| `config/game_tradeables.php` | генерируемый каталог (руками не править) |
| `database/migrations/2026_09_02_120000_add_tradeable_kind_to_market_tables.php` | схема |

### 1.2. Изменяемые файлы

| Файл | Что именно |
| --- | --- |
| `app/Services/Market/Sync/MarketOfferParser.php` | переписать разбор через `TradeOfferDecoder` |
| `app/Services/Market/Sync/MarketOfferPersister.php` | добавить новые колонки в `$updateColumns` и в insert истории |
| `app/Providers/MarketServiceProvider.php:34` | бинд `ResourceNameResolver` → `CompositeTradeableNameResolver` |
| `app/Models/MarketOffer.php`, `app/Models/MarketHistory.php` | `$fillable`/`$casts` для новых колонок |
| `app/Http/Resources/MarketOfferResource.php` | новые поля ответа |
| `app/Services/Market/MarketCatalogService.php` | фильтр `?MarketItemKind $kind` |
| `app/Services/Market/PopularItemService.php` | то же + `item_kind` в `groupBy` |
| `app/Services/Market/MarketAnalyticsService.php`, `MarketHistoryAggregator.php`, `Arbitrage/LoopArbitrageFinder.php` | `whereNotNull('price')` |
| `app/Http/Controllers/Market/{CatalogController,PopularController,AnalyticsController,BulkController}.php` | валидация и проброс `kind` |
| `app/Services/MarketCacheService.php` | `kind` в ключах кэша |
| `app/Console/Commands/LangFrontendExportCommand.php:22` | добавить `'ADN'` |
| `resources/js/lang/gameNames.js` | разбор композитного id в `marketItemName` |
| `resources/js/services/api/market.js` | параметр `kind` |
| `resources/js/views/MarketAnalytics.vue`, `PublicMarketAnalytics.vue`, `resources/js/components/market/MarketDataTable.vue` | переключатель вида и колонка вида |

### 1.3. ЗАПРЕЩЕНО менять

- `app/Services/TsoAmfService.php` и всё в `app/Services/Amf/**` — транспорт работает.
- `storage/app/parse_market.py` — уже отдаёт `type` и `slotType`.
- `docs/references/**` — только чтение.
- `lang/{en,ru,uk}/game.php` — генерируемые.
- Существующие миграции — только новая миграция.
- Интерфейс `app/Services/Market/Contracts/ResourceNameResolver.php` — меняется только реализация.

## 2. Нормативные сигнатуры

Соблюдать дословно. Имена методов и типы не переименовывать.

### 2.1. `MarketItemKind`

```php
<?php
declare(strict_types=1);

namespace App\Enums;

enum MarketItemKind: string
{
    case Resource = 'resource';
    case Buff = 'buff';
    case Adventure = 'adventure';
    case Building = 'building';

    /** Код секции локализации по умолчанию (data-sources.md §4.1). */
    public function locaSection(): string
    {
        return match ($this) {
            self::Adventure => 'ADN',
            self::Building => 'BUI',
            self::Resource, self::Buff => 'RES',
        };
    }

    /** Префикс композитного id; для ресурса префикса нет. */
    public function prefix(): ?string
    {
        return $this === self::Resource ? null : $this->value;
    }
}
```

### 2.2. `TradeSide`

```php
final readonly class TradeSide
{
    public function __construct(
        public MarketItemKind $kind,
        public string $baseName,      // ResourceName | BuffName ("Adventure", "FillDeposit", ...)
        public ?string $subject,      // resourceName_string или null
        public ?int $rawAmount,       // сырое amount со провода, null если отсутствует
        public int $units,            // единицы для цены: resource => rawAmount, иначе 1
        public ?int $recurringChance, // только для бафовых сторон
    ) {}
}
```

### 2.3. `DecodedTradeOffer`

```php
final readonly class DecodedTradeOffer
{
    public function __construct(
        public TradeSide $offer,
        public ?TradeSide $costs,   // null когда costSide === '@' (ADR-6)
        public int $totalLots,
        public int $tradeType,
    ) {}
}
```

### 2.4. `TradeOfferDecoder`

```php
final readonly class TradeOfferDecoder
{
    public const int TRADE_RES_FOR_RES = 0;
    public const int TRADE_RES_FOR_BUFF = 1;
    public const int TRADE_BUFF_FOR_RES = 2;
    public const int TRADE_BUFF_FOR_BUFF = 3;

    public const string ADVENTURE_BUFF = 'Adventure';
    public const string BUILD_BUILDING_BUFF = 'BuildBuilding';
    public const string BUILD_DEFENSE_MODE_BUILDING_BUFF = 'BuildDefenseModeBuilding';

    /** @return DecodedTradeOffer|null null — лот невалиден и должен быть пропущен */
    public function decode(string $offer, int $tradeType): ?DecodedTradeOffer;
}
```

Алгоритм `decode` (точное зеркало `cTradeObject.init`):

1. `$parts = explode('|', $offer);` — если `count($parts) !== 3` → `null`.
2. `$tradeType` вне 0..3 → `null`.
3. `offerSide` разбирается как ресурс при `type in {0,1}`, иначе как баф.
4. `$parts[1] === '@'` → `costs = null`. Иначе: ресурс при `type in {0,2}`,
   баф при `type in {1,3}`.
5. `totalLots = parseAmount(trim($parts[2])) ?? 0`.
6. Если любая разбираемая сторона дала `null` → весь лот `null`.

Разбор ресурсной стороны:

- `explode(',', $side)`; требуется `count >= 2`;
- `baseName = trim($f[0])`, непустое;
- `rawAmount = parseAmount(trim($f[1]))`, обязательно и `> 0`;
- `kind = Resource`, `subject = null`, `units = rawAmount`.

Разбор бафовой стороны:

- `explode(',', $side)`; `count >= 1`;
- `baseName = trim($f[0])`, непустое;
- `subject = trim($f[1] ?? '')`, пустая строка → `null`;
- `rawAmount = isset($f[2]) ? parseAmount(trim($f[2])) : null` (не ошибка, если null);
- `recurringChance = isset($f[3]) ? parseAmount(trim($f[3])) : null`;
- `units = 1` (ADR-5);
- `kind`: `Adventure` если `baseName === 'Adventure'`; `Building` если `baseName`
  равно `BuildBuilding` или `BuildDefenseModeBuilding`; иначе `Buff`.

`parseAmount(string $v): ?int` — `ctype_digit($v) ? (int) $v : null` (см. P-1, P-2).

### 2.5. `TradeableIdFactory`

```php
final readonly class TradeableIdFactory
{
    public const string SEPARATOR = ':';

    public function fromSide(TradeSide $side): string;

    /** @return array{kind: MarketItemKind, base: string, subject: ?string} */
    public function parse(string $itemId): array;
}
```

`fromSide` (FR-3):

| kind | результат |
| --- | --- |
| Resource | `baseName` |
| Adventure | `adventure:` + (`subject` ?? `baseName`) |
| Building | `building:` + (`subject` ?? `baseName`) |
| Buff без `subject` | `buff:baseName` |
| Buff с `subject` | `buff:baseName:subject` |

`parse` — единственное место в PHP, где разрешено разбирать `item_id`
(INV-5). Если префикса нет или он неизвестен → `Resource` со всей строкой в
`base` и `subject = null`.

### 2.6. `CompositeTradeableNameResolver`

```php
final readonly class CompositeTradeableNameResolver implements ResourceNameResolver
{
    public function __construct(
        private GameTranslationResolver $translations,
        private TradeableIdFactory $ids,
    ) {}

    public function resolve(string $id, string $fallback): string;
}
```

Алгоритм (точное зеркало `getLocalizedBuffName`, `data-sources.md` §4; таблица FR-5):

1. `['kind' => $kind, 'base' => $base, 'subject' => $subject] = $this->ids->parse($id);`
2. `Resource` → `name('RES', $base, [], $fallback)`.
3. `Adventure` → `name('ADN', $subject ?? $base, [], $fallback)`.
4. `Building` → `name('BUI', $subject ?? $base, [], $fallback)`.
5. `Buff`:
   - префикс `ProductivityBuff` | `SpeedUpPopulationGrowth` | `RecruitingBuff` →
     `name('RES', $base, [], $fallback)`;
   - префикс `AddResource` | `FillDeposit` | равно `HiredMilitary` →
     ключ `$base` (+ `'Any'`, если `$base === 'FillDeposit'` и `$subject === null`),
     аргументы `[$rawAmountOrEmpty, $subject ?? '']`;
   - префикс `ChangeColorScheme` и `$subject !== null` →
     `name('RES', $base . '_' . $subject, [], $fallback)`;
   - иначе → `name('RES', $base, [], $fallback)`.
6. Если перевод не найден, возвращается `$fallback`; если `$fallback` пуст —
   `$subject ?? $base` (никогда не полный композитный id).

> Важно: сырое `rawAmount` в `resolve()` недоступно (контракт принимает
> только id). Поэтому имя бафов с подстановкой количества вычисляется ПРИ
> СИНКЕ и кладётся в `item_name`; `resolve()` используется для каталога и там
> подставляет пустую строку вместо количества. Это осознанный компромисс,
> тест фиксирует оба поведения.

### 2.7. `MarketOfferParser` (переработанный)

Обязанности: взять сырой массив, вызвать декодер, собрать id/имена, считать
цену/объём, отдать строки для записи. Сохранить существующие: фильтр по
времени жизни лота (`config('market.offer_lifetime_hours')`), проверку
`offer_id > 0`, `created` в секундах.

Новое поведение вычислений:

```
offer_units  = decoded->offer->units
cost_units   = decoded->costs?->units
price        = cost_units === null ? null : (float) cost_units / (float) offer_units
volume       = offer_units * lots_remaining
amount       = decoded->offer->rawAmount ?? offer_units
target_amount= decoded->costs?->rawAmount ?? decoded->costs?->units
```

Счётчик пропусков возвращается вместе с результатом (INV-4) и пишется в лог
синка через `MarketSyncLogger`.

## 3. Схема базы данных

Миграция `2026_09_02_120000_add_tradeable_kind_to_market_tables.php`.

Для **обеих** таблиц `market_offers` и `market_history`:

| Колонка | Тип | Примечание |
| --- | --- | --- |
| `item_kind` | `string(16)`, NOT NULL, default `'resource'` | INV-6 |
| `item_subject` | `string(191)` nullable | |
| `target_item_kind` | `string(16)` nullable | null для лотов `@` |
| `target_item_subject` | `string(191)` nullable | |
| `trade_type` | `smallInteger` nullable | 0..3 |
| `slot_type` | `smallInteger` nullable | ADR-10 |
| `total_lots` | `integer` nullable | третий сегмент строки |

Изменения существующих колонок (обе таблицы):

- `item_id`: `string(100)` → `string(191)`;
- `target_item_id`: `string(100)` → `string(191)`, становится nullable;
- `target_item_name`: nullable;
- `target_amount`: nullable;
- `price`: nullable.

Индексы (добавить, старые не трогать):

- `market_offers`: `['server_id', 'item_kind', 'item_id']`;
- `market_history`: `['server_id', 'item_kind', 'collected_at']`.

Обязательно `down()`, симметричный `up()`. Миграция НЕ удаляет строки
(ADR-9). Для `change()` колонок нужен `doctrine/dbal` — проверь наличие в
`composer.json`; если отсутствует (Laravel 12 часто без него), используй
штатный `$table->string('item_id', 191)->change()` — в Laravel 11+ это
поддерживается нативно.

## 4. Поток данных после изменений

```
TsoAmfService::getMarketOffers()
  -> parse_market.py  (сырой JSON с type, slotType, offer, ...)
  -> MarketOfferParser::parse()
       -> TradeOfferDecoder::decode(offer, type)  -> DecodedTradeOffer|null
       -> TradeableIdFactory::fromSide()          -> item_id / target_item_id
       -> CompositeTradeableNameResolver::resolve()-> item_name / target_item_name
  -> MarketOfferPersister::persist()  (upsert + history)
  -> сервисы чтения (каталог / аналитика / арбитраж) -> API -> SPA
```

## 5. API

### 5.1. Новые поля `MarketOfferResource`

```json
{
  "item_kind": "adventure",
  "item_subject": "MadHenry",
  "target_item_kind": "resource",
  "target_item_subject": null
}
```

### 5.2. Параметр `kind`

Валидация в контроллерах:

```php
$validated = $request->validate([
    'kind' => ['sometimes', 'string', 'in:all,resource,buff,adventure,building'],
]);
$kind = ($validated['kind'] ?? 'all') === 'all'
    ? null
    : MarketItemKind::from($validated['kind']);
```

`null` означает «без фильтра». Ключ кэша обязательно включает `kind` (P-22).

### 5.3. Каталог

`MarketCatalogService::goods(string $serverId, ?MarketItemKind $kind = null)` и
`targets(string $serverId, string $itemId, ?MarketItemKind $kind = null)`.
Приватный `distinctPairColumn()` получает дополнительно имя колонки вида
(`item_kind` или `target_item_kind`) и добавляет `->where(...)` к ОБОИМ
запросам (offers и history) до union. Сортировка остаётся
`SORT_NATURAL | SORT_FLAG_CASE`. В ответ каждого элемента добавляется `kind`.

## 6. Локализация для SPA

`app/Console/Commands/LangFrontendExportCommand.php`:

```php
private const array FRONTEND_GAME_SECTIONS = ['ADN', 'BUI', 'LAB', 'RES', 'SPE'];
```

После правки обязательно:

```bash
php artisan tso:lang:export-frontend
```

Результат попадает в `resources/js/lang/generated/{en,ru,uk}.json` и должен быть
закоммичен.

## 7. Фронтенд

`resources/js/lang/gameNames.js` — единственная точка разбора (ADR-12):

```js
const KIND_SECTION = { adventure: 'ADN', building: 'BUI', buff: 'RES' }

export function parseTradeableId(rawId) {
  const [head, ...rest] = String(rawId ?? '').split(':')
  if (!KIND_SECTION[head]) return { kind: 'resource', base: rawId, subject: null }
  if (head === 'buff') return { kind: 'buff', base: rest[0] ?? '', subject: rest[1] ?? null }
  return { kind: head, base: rest[0] ?? '', subject: null }
}
```

`marketItemName(name, id)` порядок поиска:

1. разобрать id;
2. поиск в секции `KIND_SECTION[kind]` по `subject ?? base`;
3. существующий `gameAnyLookup(rawName)`;
4. `rawName`;
5. `humanizeGameId(subject ?? base)` — НИКОГДА не полный композитный id.

В таблице `MarketDataTable.vue` добавляется бейдж вида; в `MarketAnalytics.vue`
и `PublicMarketAnalytics.vue` — сегментированный фильтр вида, пробрасываемый в
`kind` через `resources/js/services/api/market.js`.

## 8. Каталог статики

`ImportTradeablesCatalog` — по образцу существующей
`app/Console/Commands/ImportProductionCatalog.php` (тот же `XMLReader`, та же
запись PHP-массива, те же флаги `LIBXML_NONET | LIBXML_COMPACT`).

Структура `config/game_tradeables.php`:

```php
return [
    'adventures' => [
        'MadHenry' => ['id' => 1, 'tradable' => true, 'level_range' => 'Middle', 'difficulty' => 8],
    ],
    'buffs' => [
        'FillDeposit_Fishfood' => ['id' => 3, 'tradable' => false, 'resource' => 'Fish', 'amount' => 100],
    ],
];
```

Сортировка ключей — `ksort` (AC-10: детерминированный вывод).
