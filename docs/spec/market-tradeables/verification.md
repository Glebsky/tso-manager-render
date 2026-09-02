# Verification: market-tradeables

## 1. Гейты (после каждого этапа)

```bash
php artisan test
./vendor/bin/pint --test
./vendor/bin/phpstan analyse
npm run build      # только если трогал resources/js
```

Все четыре должны быть зелёными (AC-11). Запрещено: добавлять baseline
phpstan, глушить ошибки через `@phpstan-ignore`, помечать тесты `skip`.

## 2. Фикстуры

| Файл | Назначение |
| --- | --- |
| `docs/references/amf/call05_response.decoded.json` | реальный ответ `TradeWindowHandler.getUserTradesHistory` — образец формы данных |
| `tests/Fixtures/Market/offers_resource_only.json` | регресс INV-2 |
| `tests/Fixtures/Market/offers_tradeables.json` | бафы, приключения, постройки, лот `@`, битые строки |

Форма одного элемента фикстуры (точно как отдаёт `parse_market.py`):

```json
{
  "id": 987654321,
  "senderID": 4242,
  "senderName": "SomePlayer",
  "type": 2,
  "slotType": 0,
  "created": 1756704000000,
  "offer": "Adventure,MadHenry,0|Coin,150000|1",
  "lotsRemaining": 1
}
```

`created` — в МИЛЛИСЕКУНДАХ (парсер делит на 1000). В тестах всегда
фиксируй время через `Carbon::setTestNow()`, иначе фильтр жизни лота
(6 часов из `config/market.php`) выбросит твои фикстуры и тест будет
«проходить» на пустом результате.

## 3. Тест-план: AC → тест

| AC | Тест | Тип |
| --- | --- | --- |
| AC-1 | `TradeOfferDecoderTest::test_adventure_offer_side` + `MarketOfferParserTest::test_adventure_row` | unit |
| AC-2 | `MarketOfferParserTest::test_resource_lot_is_byte_identical` | unit (golden) |
| AC-3 | `TradeOfferDecoderTest::test_buff_with_subject` | unit |
| AC-4 | `TradeOfferDecoderTest::test_adventure_on_cost_side` | unit |
| AC-5 | `TradeOfferDecoderTest::test_buff_without_subject` | unit |
| AC-6 | `MarketOfferParserTest::test_at_sign_lot_has_null_price` | unit |
| AC-7 | `Feature/Market/CatalogKindFilterTest::test_goods_kind_adventure` | feature |
| AC-8 | `Feature/Market/OfferNamesTest::test_no_raw_ids_in_response` | feature |
| AC-9 | `MarketOfferParserTest::test_unknown_trade_type_is_counted` | unit |
| AC-10 | `Feature/Console/ImportTradeablesCatalogTest::test_output_is_deterministic` | feature |
| INV-1 | `TradeOfferDecoderTest::test_non_numeric_amount_is_rejected` | unit |
| INV-2 | тот же тест, что AC-2 | unit |
| INV-4 | `Feature/Market/MarketSyncTradeablesTest::test_unparsed_offers_are_not_persisted` | feature |
| INV-6 | `Feature/Market/MigrationDefaultsTest::test_existing_rows_get_resource_kind` | feature |
| FR-5 | `CompositeTradeableNameResolverTest` (по одному кейсу на каждую строку таблицы FR-5) | unit |
| FR-9 | `CatalogKindFilterTest::test_invalid_kind_returns_422` | feature |
| FR-12 | `Feature/Console/CleanupLegacyTradeablesTest` (dry-run и реальный запуск) | feature |

## 4. Обязательные негативные кейсы декодера

Каждый из них должен вернуть `null` без исключения:

| Ввод | `type` |
| --- | --- |
| `"Marble,100"` | 0 |
| `"Marble,100\|Coin,500\|4\|extra"` | 0 |
| `""` | 0 |
| `"Marble,abc\|Coin,500\|1"` | 0 |
| `"Marble,100\|Coin,500\|1"` | 7 |
| `",100\|Coin,500\|1"` | 0 |
| `"Marble,0\|Coin,500\|1"` | 0 |

А эти — НЕ `null` (частая ошибка перестраховки):

| Ввод | `type` | Ожидание |
| --- | --- | --- |
| `"Adventure,MadHenry,0\|Coin,150000\|1"` | 2 | adventure, `units = 1` |
| `"ProductivityBuffLvl3,,1\|Coin,5000\|1"` | 2 | buff, `subject = null` |
| `"HiredMilitary,,100,50\|Coin,900\|3"` | 2 | buff, `recurringChance = 50` |
| `"PremiumAccount,Days7\|Coin,900\|1"` | 2 | buff из 2 полей, `rawAmount = null` |
| `"Marble,100\|@\|4"` | 0 | `costs = null`, `price = null` |

## 5. Ручная проверка перед сдачей

1. Прогнать синк: `php artisan market:sync` (или через
   `POST /api/market/sync`).
2. Проверить в БД:

```sql
SELECT item_kind, count(*) FROM market_offers GROUP BY item_kind;
SELECT DISTINCT item_id FROM market_offers WHERE item_kind = 'adventure' LIMIT 20;
SELECT count(*) FROM market_offers WHERE item_id = 'Adventure';   -- ожидается 0
SELECT count(*) FROM market_offers WHERE item_name = item_id AND item_kind <> 'resource';
```

3. Открыть страницу аналитики рынка и убедиться, что:
   - в списке товаров есть конкретные приключения на русском;
   - есть бафовые лоты;
   - фильтр вида переключает список;
   - нет текстов вида `adventure:MadHenry`.
4. Проверить лог синка: `market_sync_logs` содержит счётчик пропущенных
   лотов и он мал (единицы, а не сотни). Большое число = неучтённый
   формат, разбираться, а не закрывать задачу.

## 6. Когда задача СЧИТАЕТСЯ НЕ выполненной

- Хоть один гейт красный.
- В БД появляются новые строки с `item_id = 'Adventure'`.
- Счётчик пропущенных лотов растёт пропорционально числу лотов.
- Фильтр `kind` работает, но без него выборка изменилась (сломана обратная
  совместимость, ADR-11).
- В коде появился второй место разбора `item_id` помимо
  `TradeableIdFactory::parse()` (INV-5).
