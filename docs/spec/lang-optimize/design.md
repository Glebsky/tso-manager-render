<!-- SDD package: lang-migration v0.4 -->
<!-- English is the default locale; Russian is additional. -->

# Design: архитектура локализации

## Архитектурный обзор

```text
en_lang.xml ─┐                       ┌─ lang/en/game.php ─┐
             ├─ tso:lang:import ─────┤                    ├─ GameTranslationResolver
ru_lang.xml ─┘                       └─ lang/ru/game.php ─┘          │
                                                                    ├─ Backend services/resources
lang/<locale>/ui.php ── export ── resources/js/lang/generated/*.json └─ Vue lang module
```

### Границы компонентов

- **Import command** — CLI transport: принимает параметры, запускает parser/writer, печатает диагностику и exit code.
- **XML parser** — потоково читает `oasis/translations/s/t`, не зависит от Artisan и не интерпретирует текст.
- **Translation writer** — детерминированно и атомарно создаёт `lang/<locale>/game.php`.
- **GameTranslationResolver** — точный lookup по `(locale, section, raw ID)`, fallback и строгая интерполяция.
- **Frontend exporter** — создаёт статические JSON bundles из Laravel lang source.
- **Frontend lang module** — предоставляет единые `t()` и `game()` для Vue.

### Fallback

```text
Application UI, locale en: en → translation key
Application UI, locale ru: ru → en → translation key
Game data, locale en:      en → raw game ID
Game data, locale ru:      ru → en → raw game ID
```

Английский игровой интерфейс не откатывается на русский: это исключает смешивание языков в основной locale.

### Работа с raw ID

Игровые ID могут содержать точки, пробелы и `/`. Поэтому resolver сначала загружает массив секции, а затем обращается к нему по точному raw ID. Запрещено превращать ID в Laravel dot-key или выполнять нечёткий поиск по lowercase/удалённым символам.

### Интерполяция

Поддерживаются только строгие формы `{0}` и `{0,RES}`. Во второй форме параметр может быть разрешён через указанную игровую секцию. Конструкции вроде `{Christmas}` остаются литералами. Рекурсивное разрешение имеет ограничение глубины и защиту от циклов.

### Данные Market Analytics

`item_id` и `target_item_id` являются каноническими. Сохранённые `item_name` и `target_item_name` считаются legacy-cache и не выбирают locale. API/UI локализуют сущность по ID при чтении.

## 7. Целевая структура

```text
lang/
├── ru/
│   ├── auth.php
│   ├── validation.php
│   ├── ui.php
│   ├── errors.php
│   └── game.php              # generated from ru_lang.xml
└── en/
    └── game.php              # generated from en_lang.xml; UI added later

resources/js/lang/
├── index.js                  # t(), game(), interpolation, fallback
└── generated/
    ├── ru.json               # generated from Laravel lang files
    └── en.json               # game catalog now; UI added later

tests/Fixtures/Lang/
├── valid-lang.xml
├── malformed-lang.xml
└── duplicate-ids-lang.xml
```

`game.php` должен возвращать массив, сгруппированный по исходным секциям. Исходные ID сохраняются без изменения:

```php
return [
    'RES' => [
        'BronzeOre' => 'Медная руда',
    ],
    'SPE' => [
        'General' => 'Генерал',
    ],
];
```

Потребитель не должен строить строку `game.RES.<id>` для прямого вызова `__()`: игровые ID могут содержать точки или другие символы, имеющие специальное значение для Laravel. Сначала загружается массив секции, затем выполняется точный lookup по исходному ID.
