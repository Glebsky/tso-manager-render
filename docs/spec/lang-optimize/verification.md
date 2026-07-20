<!-- SDD package: lang-migration v0.4 -->
<!-- English is the default locale; Russian is additional. -->

# Verification: локализация и игровые переводы

Каждая проверка должна быть трассируема к требованиям и критериям из [requirements.md](requirements.md). Финальный отчёт реализации должен содержать таблицу `Requirement → Code → Test → Status`.

## 14. Стратегия тестирования

### Unit tests

- разбор всех секций;
- Unicode, кавычки, переносы и строгие placeholders `{0}` / `{1,RES}`;
- литералы в фигурных скобках, например `{Christmas}`, не интерполируются;
- пустые ID/text;
- одинаковые и конфликтующие дубликаты;
- malformed XML;
- deterministic ordering/output;
- точный lookup ID, включая точки, пробелы, underscore и нестандартные символы;
- fallback resolver;
- безопасная интерполяция placeholders.

### Feature/command tests

- успешный `tso:lang:import`;
- ненулевой exit code при ошибке;
- существующий output не повреждается;
- locale option создаёт output только в разрешённой locale-директории;
- Market Sync работает без внешнего `lang.txt`;
- legacy translation routes отсутствуют.

### Frontend tests или проверяемые сценарии

- общий `t()` и `game()` возвращают пе��евод и fallback;
- экран не выполняет translation API request;
- ресурс, специалист, бафф и задание отображаются по ID;
- placeholder подставляется одинаково на всех экранах;
- отсутствуют смешанные двуязычные fallback-строки.

### Статические проверки

В CI добавить проверки на:

- использование `/api/lang/res`;
- runtime references к `lang.txt`;
- новые прямые пользовательские строки вне разрешённых файлов;
- ручное изменение generated-файлов без воспроизводимого импорта.

## 16. Наблюдаемость

Import-команда должна сообщать:

- locale и source path;
- число секций;
- число импортированных записей;
- число пустых/пропущенных записей;
- число одинаковых дубликатов;
- список конфликтующих ID;
- путь и checksum созданного файла.

Runtime может считать отсутствующие игровые ключи в development/test, но не должен засорять production log повторениями одного ID.
