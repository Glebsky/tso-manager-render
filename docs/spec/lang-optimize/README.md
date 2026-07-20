<!-- SDD package: lang-migration v0.4 -->
<!-- English is the default locale; Russian is additional. -->

# SDD: унификация локализации и импорт игровых переводов

- **Статус:** Draft для ревью
- **Версия пакета:** 0.4
- **Проект:** Laravel 10 / Vue 3
- **Основной язык:** English (`en`)
- **Дополнительный язык:** Russian (`ru`)
- **Игровые XML:** `en_lang.xml` (`en_uk`) и `ru_lang.xml` (`ru_ru`)

## Назначение

Этот каталог является источником истины для миграции с `LangController` и runtime-парсинга XML на стандартную систему переводов Laravel и единый frontend lang-модуль.

## Документы

1. [Requirements](requirements.md) — контекст, цели, требования, инварианты, сценарии и acceptance criteria.
2. [Design](design.md) — целевая архитектура, импорт, resolver, frontend bundle, fallback и хранение данных.
3. [Implementation plan](implementation-plan.md) — этапы разработки, deployment, решения и открытые вопросы.
4. [Verification](verification.md) — unit, feature, frontend, статические проверки и наблюдаемость.

## Главные решения

- `APP_LOCALE=en` и `APP_FALLBACK_LOCALE=en`.
- Русский подключается как дополнительная locale `ru`.
- Игровой fallback: `en → raw ID`; `ru → en → raw ID`.
- XML импортируются build-time; production runtime их не читает.
- Все 40 секций импортируются без allow-list.
- Raw game ID сохраняются без нормализации.
- `lang/en/game.php` и `lang/ru/game.php` коммитятся в Git.
- Frontend использует generated static bundle и не вызывает translation API.

## Рабочий процесс SDD

1. Изменить и согласовать `requirements.md`.
2. При необходимости обновить `design.md`.
3. Обновить `implementation-plan.md` и `verification.md`.
4. Реализовать изменение небольшими этапами.
5. Сопоставить реализацию со всеми `FR`, `NFR`, `INV` и `AC`.
6. Коммитить документацию вместе с соответствующим кодом и тестами.

## Definition of Done

Задача завершена только после выполнения критериев из `requirements.md`, проверок из `verification.md` и условий Definition of Done из `implementation-plan.md`.
