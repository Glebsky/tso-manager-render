<!-- SDD package: lang-migration v0.4 -->
<!-- English is the default locale; Russian is additional. -->

# Implementation plan: миграция локализации

## 13. План реализации

### Этап 1. Инвентаризация

- Собрать все user-facing строки в Vue, Blade, controllers/actions/resources.
- Составить список игровых lookup и определить для каждого секцию и ID.
- Зафиксировать формат реального `lang.txt` на безопасной fixture-выборке.
- Найти места хранения локализованных названий в БД и API.

### Этап 2. Laravel locale foundation

- Создать полные базовые файлы `lang/en/ui.php`, `errors.php`, `auth.php`, `validation.php`.
- Создать соответствующую дополнительную структуру `lang/ru` для русских значений.
- Перенести конфигурацию locale/fallback в environment с default `en`.
- Унифицировать HTML `lang` и locale-aware форматирование дат.

### Этап 3. Импортёр

- Реализовать parser отдельно от Artisan transport.
- Добавить DTO результата импорта и диагностики.
- Реализовать атомарный deterministic writer для `lang/ru/game.php`.
- Добавить command tests и XML fixtures.

### Этап 4. Backend migration

- Добавить единый resolver игровых переводов поверх Laravel Translator.
- Перевести `MarketSyncService` и остальные backend-потребители.
- Обеспечить lookup по ID при выдаче Market Analytics.

### Этап 5. Frontend migration

- Добавить экспорт Laravel translations в generated JSON.
- Добавить общий frontend lang module/composable.
- Последовательно мигрировать `AccountDetail.vue`, `Tasks.vue`, `MarketAnalytics.vue`, `PublicMarketAnalytics.vue`, затем остальные экраны.
- Удалить локальные fallback и normalization-функции.

### Этап 6. Legacy removal

- Удалить translation API routes, `LangController` и `LangParserService`.
- Проверить отсутствие `lang.txt` runtime references и translation HTTP calls.

### Этап 7. Полнота локалей и контроль качества

- Гарантировать полноту `lang/en` как основной локали.
- Перенести существующие русские строки в `lang/ru` и проверить дополнительную локаль.
- Проверить основные пользовательские сценарии и публичную Market Analytics на английском по умолчанию.
- Запустить тесты, Pint, PHPStan и frontend build.
## 15. Deployment и rollback

1. Добавить и проверить `lang/ru` и generated bundle.
2. Развернуть код, пока legacy endpoint ещё доступен, но новые потребители уже используют bundle.
3. Проверить ключевые экраны и Market Sync.
4. В следующем безопасном шаге удалить legacy endpoint/classes.
5. При rollback возвращается пре��ыдущий код; импорт не изменяет БД и не требует отдельного rollback.

`lang.txt` не обязан присутствовать на production. Импорт выполняется локально или в контролируемом build/release-процессе, а generated translation files коммитятся в Git.
## 17. Открытые вопросы для утверждения

1. **Можно ли коммитить исходные `ru_lang.xml` и `en_lang.xml` в Git?** Технически они пригодны для импорта, но решение зависит от лицензии игры. Generated `lang/ru/game.php` и `lang/en/game.php` коммитятся обязательно.
2. **Нужно ли импортировать записи с пустым `text`?** В предоставленных файлах пустых значений нет. Для будущих версий по умолчанию они пропускаются с отчётом.
3. **Нужно ли считать английский каталог допустимым fallback для отсутствующих русских игровых ключей?** Да: используется `ru → en → ID`. Обратный fallback `en → ru` не используется, чтобы основной английский интерфейс не смешивался с русским.
4. **Должны ли пользовательские ошибки API переводиться backend-ом или frontend-ом по стабильному error code?** Рекомендуется code + frontend translation, с локализованным backend message для совместимости.
5. **Можно ли перестать сохранять `item_name` и `target_item_name` в Market tables?** Рекомендуется отдельная миграция после перевода API на ID; в этой задаче допускается оставить поля как legacy-cache.
6. **Нужен ли сторонний `vue-lang`?** Для двух локалей можно использовать небольшой собственный composable, если переключение выполняется при загрузке приложения. Пакет оправдан, если требуются runtime-переключение без перезагрузки, pluralization и locale routing.
7. **Какие секции `lang.txt` должны попасть в frontend bundle?** По умолчанию экспортируются только реально используемые секции, чтобы не раздувать JS; backend сохраняет полный импорт.
## 18. Принятые по умолчанию решения

До отдельного изменения spec считаются принятыми следующие решения:

- английский — default и application fallback locale; русский — дополнительная locale;
- игровой resolver использует `en → ID` и `ru → en → ID`;
- `ru_lang.xml` и `en_lang.xml` импортируются build-time, не runtime;
- `lang/ru/game.php` и `lang/en/game.php` коммитятся в Git;
- импортируются все 40 секций обеих локалей;
- raw game IDs сохраняются без нормализации;
- игровые каталоги EN и RU создаются сейчас; английский UI должен быть полным в рамках этой миграции, русский хранится как дополнительный;
- frontend использует generated static bundle;
- translation HTTP endpoints удаляются;
- несовпадение состава RU/EN является штатным и обрабатывается fallback-цепочкой.
## 19. Definition of Done

Задача завершена, когда выполнены все AC, закрыты или явно отложены открытые вопросы, оба generated-файла воспроизводимы документированными командами из предоставленных XML, а существующие основные пользовательские сценарии работают без `LangController`, `LangParserService` и runtime-доступа к XML.
