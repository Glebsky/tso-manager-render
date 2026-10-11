# Requirements: market-tradeables

> Документ восстановлен после реализации: идентификаторы FR/INV/AC уже
> использовались в `design.md`, `implementation-plan.md` и `verification.md`,
> но сам файл отсутствовал. Формулировки сведены из этих документов, ADR
> (`decisions.md`) и реализованного кода. При расхождении приоритет —
> `decisions.md`, затем `design.md`.

## 1. Глоссарий

| Термин | Значение |
| --- | --- |
| Лот | Элемент ответа `TradeWindowHandler.GetAvailableOffers` (`dTradeObjectVO`). |
| Строка лота | Поле `offer`: `<offerSide>\|<costSide>\|<totalLots>`. |
| `type` | `dTradeObjectVO.type` (`TRADE_TYPE`): 0 res/res, 1 res/buff, 2 buff/res, 3 buff/buff. |
| Сторона | Предлагаемая (`offer`) или требуемая (`costs`) часть лота. |
| Tradeable | Торгуемая сущность вида `resource`, `buff`, `adventure`, `building` (`MarketItemKind`). |
| Композитный `item_id` | `adventure:<name>`, `building:<name>`, `buff:<base>[:<subject>]`; для ресурса — имя ресурса без префикса. |
| Лот `@` | Лот, у которого `costSide == "@"` — цены нет. |

## 2. Функциональные требования

- **FR-1.** Парсер декодирует строку лота с учётом `type` (ADR-1); строка должна содержать ровно три сегмента, иначе лот отклоняется (ADR-2).
- **FR-2.** Ресурсная сторона — `name,amount`; бафовая — `buffName,resourceName,amount[,recurringChance]`, также допускается форма из двух полей. Приключение — баф `Adventure`, постройка — баф `BuildBuilding` / `BuildDefenseModeBuilding`.
- **FR-3.** `item_id` формируется только `TradeableIdFactory::fromSide()` по таблице из `design.md` §2.5.
- **FR-4.** Вид и субъект сохраняются в отдельных колонках `item_kind`, `item_subject`, `target_item_kind`, `target_item_subject` таблиц `market_offers` и `market_history` (ADR-4).
- **FR-5.** Имена локализуются по секциям клиента: приключение → `ADN`, постройка → `BUI`, баф/ресурс → `RES`, с правилами подстановки для `AddResource*`, `FillDeposit*`, `HiredMilitary`, `ChangeColorScheme*` (`design.md` §2.6, `data-sources.md` §4).
- **FR-6.** Единица бафа/приключения для расчёта цены равна 1 (ADR-5).
- **FR-7.** Лоты `@` сохраняются с `price = null`; аналитика, история и арбитраж исключают их через `whereNotNull('price')` (ADR-6).
- **FR-8.** Классификация вида — только по протоколу; XML-каталог используется справочно (ADR-7).
- **FR-9.** Каталог, популярные товары, аналитика и bulk API принимают `kind ∈ {all, resource, buff, adventure, building}`; иное значение → `422`. `kind` входит в ключи кэша.
- **FR-10.** API только добавляет поля `item_kind`, `item_subject`, `target_item_kind`, `target_item_subject`; без `kind` выборка не меняется (ADR-11).
- **FR-11.** SPA разбирает композитный id в одном месте (`resources/js/lang/gameNames.js`) и показывает фильтр и бейдж вида (ADR-12).
- **FR-12.** `CleanupLegacyTradeablesCommand` удаляет старые испорченные записи (`item_id = 'Adventure'` и т. п.) и поддерживает `--dry-run` (ADR-9).
- **FR-13.** `ImportTradeablesCatalog` генерирует `config/game_tradeables.php` из игровых XML детерминированно.

## 3. Инварианты

- **INV-1.** Нечисловое или нулевое количество отклоняет лот; приведение `(int)` от произвольной строки запрещено.
- **INV-2.** Для ресурсных лотов (`type = 0`) сохраняемые значения побайтово совпадают с поведением до фичи.
- **INV-3.** Формат каждой стороны определяется только `type`, а не эвристикой по содержимому.
- **INV-4.** Нераспознанные лоты не сохраняются; их число возвращается парсером и пишется в лог синка.
- **INV-5.** Разбор `item_id` в PHP выполняется только в `TradeableIdFactory::parse()`.
- **INV-6.** Существующие строки после миграции получают `item_kind = 'resource'`.

## 4. Acceptance criteria

| AC | Критерий |
| --- | --- |
| AC-1 | `Adventure,MadHenry,0\|Coin,150000\|1` (`type = 2`) → `item_kind = adventure`, `item_id = adventure:MadHenry`, `units = 1`. |
| AC-2 | Ресурсный лот даёт те же значения, что до изменения (INV-2). |
| AC-3 | Баф с субъектом (`FillDeposit,Fish,…`) → `buff:FillDeposit:Fish`. |
| AC-4 | Приключение на стороне цены (`type = 1`) декодируется как adventure. |
| AC-5 | Баф без субъекта (`ProductivityBuffLvl3,,1`) → `subject = null`. |
| AC-6 | Лот `@` сохраняется с `price = null`. |
| AC-7 | Фильтр `kind=adventure` в каталоге возвращает только приключения. |
| AC-8 | В ответах API и SPA нет сырых композитных id вместо имён. |
| AC-9 | Неизвестный `type` не роняет синк и учитывается в счётчике пропусков. |
| AC-10 | Повторный запуск `ImportTradeablesCatalog` даёт идентичный файл. |
| AC-11 | Гейты `php artisan test`, `pint --test`, `phpstan analyse`, `npm run build` зелёные. |

Соответствие AC → тестам — `verification.md` §3.
