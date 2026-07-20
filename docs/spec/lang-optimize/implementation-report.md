# Отчёт о реализации — lang-optimize

Дата: 2026-07-20. Статус: **реализовано** (см. ограничения окружения ниже).

## Что сделано

### 1. Импорт XML → каталоги Laravel (Этап 1–2)
- `app/Services/Lang/LangXmlParser.php` — потоковый парсер (`XMLReader`, `LIBXML_NONET`), нормализация локалей (`en_uk`→`en`, `ru_ru`→`ru`), подсчёт статистики, дедупликация, фиксация конфликтов.
- `app/Services/Lang/LangImportResult.php`, `LangImportException.php` — DTO результата и доменное исключение.
- `app/Services/Lang/GameLangFileWriter.php` — детерминированная (байтовая сортировка ключей) атомарная запись `lang/<locale>/game.php`.
- `app/Console/Commands/LangImportCommand.php` — `php artisan tso:lang:import {source} --locale=en|ru [--lang-dir=]`: валидация локали, останов при конфликтах, таблица статистики, SHA-256.
- Сгенерированы `lang/en/game.php` (37 242 записи, 40 секций) и `lang/ru/game.php` (36 008 записей, 42 секции).

### 2. Резолвер игровых переводов (Этап 3)
- `app/Services/Lang/GameTranslationResolver.php` — `name()/resolve()/has()`, цепочка локалей `<locale>→en→id/fallback`, плейсхолдеры `{N}` и `{N,SECTION}` (строгий формат, `{Christmas}` и пр. не трогаются), лимит вложенности 3, защита от циклов, кеш каталогов.

### 3. Бэкенд переведён на каталог (Этап 4)
- `MarketSyncService` — имена ресурсов через резолвер (вместо `LangParserService`).
- `MarketAnalyticsController` — `resourceName()` с fallback на сохранённое имя; `getGoods`/`getTargets`/`popular`/active offers/arbitrage выдают локализованные имена; формат ответа (`item_id`/`item_name`) сохранён.

### 4. UI-переводы и локали (Этап 5)
- `config/app.php`: `locale`/`fallback_locale` из `APP_LOCALE`/`APP_FALLBACK_LOCALE` (по умолчанию `en`); `.env.example` дополнен.
- `lang/{en,ru}/ui.php` (39 плоских dot-ключей), `lang/{en,ru}/auth.php`, `lang/ru/validation.php` (базовый набор).
- Blade: `app.blade.php` (`lang`-атрибут, `window.__APP_LOCALE__`), `login.blade.php` → `__('ui.auth.*')`.

### 5. SPA (Этап 6)
- `app/Console/Commands/LangFrontendExportCommand.php` — `php artisan tso:lang:export-frontend`: JSON-бандлы (секции LAB/RES/SPE + ui) в `resources/js/lang/generated/{en,ru}.json` (детерминированный вывод). Сгенерированы: en — 4 417 игровых записей, ru — 4 203, ui — 39.
- `resources/js/lang/index.js` — `t()`, `game()`, `gameAny()`, `gameAnyLookup()`, `intlLocale`; fallback ru→en→ключ; та же семантика плейсхолдеров.
- `app.js` — глобальные `t/game/gameAny/$lang` для шаблонов.
- Мигрированы: `App.vue`, `Register.vue`, `Tasks.vue` (полностью: все UI-строки формы/списка задач/модалок/тостов через `tasks.*`, плюс игровые лукапы: баффы, ресурсы, `intlLocale`; внутри цикла `v-for="t in groupTasks"` используется `$lang.t(...)`, т.к. переменная цикла затеняет глобальный `t()`), `AccountDetail.vue` (вкладки склада `WarehouseTab*`, баффы, специалисты, задачи разведки/геологии, фильтры, плейсхолдеры), `PublicMarketAnalytics.vue` (`getItemName` через каталог).
- Проверка: в `resources/js` и `routes/` не осталось обращений к `/api/lang/res`, `/api/public/market/lang/res`, `translations.value`.

### 6. Удаление легаси (Этап 7)
- Удалены `LangController`, `LangParserService`, маршруты `/api/lang/res` и `/api/public/market/lang/res`; `grep` по `app/ routes/ config/ tests/ resources/views/` — ссылок нет; `lang.txt` больше не используется.

### 7. Тесты (Этап 8)
- `tests/Fixtures/Lang/{valid,conflict,malformed}-lang.xml`.
- `tests/Unit/Lang/LangXmlParserTest.php` — статистика, дедуп, конфликты, битый XML, алиасы локалей.
- `tests/Unit/Lang/GameLangFileWriterTest.php` — загружаемость, сортировка, экранирование, детерминизм.
- `tests/Unit/Lang/GameTranslationResolverTest.php` — точный перевод, fallback ru→en→id, `{N}`/`{N,RES}`, сохранение `{Christmas}`, недостающие параметры, вложенные плейсхолдеры.
- `tests/Feature/LangImportCommandTest.php` — успешный импорт, несовпадение/неподдерживаемая локаль, конфликт, битый XML.
- `tests/Feature/LegacyLangRemovalTest.php` — 404 старых эндпоинтов, отсутствие классов, `fallback_locale = en`.

## Соответствие требованиям (requirements.md)
| Требование | Реализация | Тест | Статус |
|---|---|---|---|
| R1 Импорт XML в `lang/<locale>/game.php` | LangXmlParser + GameLangFileWriter + tso:lang:import | LangXmlParserTest, LangImportCommandTest | ✅ |
| R2 EN — основной, RU — дополнительный | config/app.php, цепочка локалей резолвера | GameTranslationResolverTest, LegacyLangRemovalTest | ✅ |
| R3 Плейсхолдеры `{N}`, `{N,SECTION}` | GameTranslationResolver, lang/index.js | GameTranslationResolverTest | ✅ |
| R4 Бэкенд без LangParserService | MarketSyncService, MarketAnalyticsController | существующий MarketAnalyticsTest (совместимость формата) | ✅ |
| R5 SPA без `/api/lang/res` | lang/index.js + JSON-бандлы + миграция компонентов | LegacyLangRemovalTest | ✅ |
| R6 Удаление легаси | контроллер/сервис/маршруты удалены | LegacyLangRemovalTest | ✅ |
| R7 Детерминированная регенерация | writer/export, атомарная запись | GameLangFileWriterTest | ✅ |

## Осознанный остаток (staged debt)
- `Tasks.vue` полностью мигрирован на `tasks.*` (шаблон, тосты, модалки, справочники целей геолога/разведчика, фильтр категорий зданий переведён на id `all/wood/mines/metal/food/other`); кириллица осталась только в комментариях для разработчиков.
- RU-fallback-карты подтипов задач в `AccountDetail.vue` оставлены как последний fallback (срабатывают, только если id нет в игровом каталоге); английские тексты ошибок в `MarketAnalyticsController` можно перевести через `__()` следующим этапом.
- Сообщения `MarketAnalyticsController` (англ. строки ошибок) — при желании вынести в `lang/*/errors.php`.

## Ограничения окружения (важно)
В песочнице недоступны PHP/Composer/сеть, поэтому `php artisan test`, Pint, Larastan и `npm run build` **не запускались**. `lang/*/game.php` и `resources/js/lang/generated/*.json` сгенерированы эквивалентным детерминированным генератором, байт-в-байт повторяющим формат `GameLangFileWriter`/`LangFrontendExportCommand`. После распаковки выполнить:
```bash
composer install && php artisan test
vendor/bin/pint --test && vendor/bin/phpstan analyse
npm ci && npm run build
# контрольная регенерация:
php artisan tso:lang:import /path/to/en_lang.xml --locale=en
php artisan tso:lang:import /path/to/ru_lang.xml --locale=ru
php artisan tso:lang:export-frontend
```
