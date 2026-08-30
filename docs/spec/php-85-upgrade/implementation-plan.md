# Implementation Plan: Upgrade to PHP 8.5

- **Пакет:** php-85-upgrade v1.0
- **Статус:** Draft / Planning

## 1. Пакеты Работ (Work Packages Breakdown)

Переход на PHP 8.5 разделен на 4 последовательных этапа (Work Packages) для обеспечения изолированности изменений и возможности легкого отката.

| ID | Пакет Работ | Описание | Приоритет | Затронутые файлы |
| :--- | :--- | :--- | :--- | :--- |
| **WP1** | Infrastructure & Config | Обновление Dockerfile и конфигурации Composer | P0 | `Dockerfile`, `composer.json` |
| **WP2** | Dependency Verification | Синхронизация composer.lock и проверка версий | P0 | `composer.lock` |
| **WP3** | Code Adaptation & Gates | Устранение депрекейшенов, адаптация тестов, прогон гейтов | P0 | `app/`, `tests/`, `phpstan-baseline.neon` |
| **WP4** | Governance & Docs | Обновление конституции проекта и правил агентов | P1 | `docs/spec/constitution.md`, `AGENTS.md` |

---

## 2. Пошаговый План Реализации

### 2.1. WP1 — Infrastructure & Config

1. Изменить версию базового образа PHP в [`Dockerfile`](file:///c:/OSPanel/home/admin/Dockerfile):
   - Заменить `FROM php:8.4-fpm-alpine AS base` на `FROM php:8.5-fpm-alpine AS base`.
2. Обновить ограничения версии в [`composer.json`](file:///c:/OSPanel/home/admin/composer.json):
   - Заменить `"php": "^8.4"` на `"php": "^8.5"`.
   - Заменить `"platform": { "php": "8.4.0" }` на `"platform": { "php": "8.5.0" }`.

### 2.2. WP2 — Dependency Verification & Lock Update

1. Выполнить команду обновления зависимостей в окружении PHP 8.5 (или через Composer CLI):
   ```bash
   composer update --with-all-dependencies
   ```
2. Проверить отсутствие конфликтов зависимостей и корректность генерации `composer.lock`.

### 2.3. WP3 — Code Adaptation & Quality Gates Execution

1. Запустить набор модульных и интеграционных тестов:
   ```bash
   php artisan test
   ```
2. Запустить форматирование PHP Pint:
   ```bash
   ./vendor/bin/pint --test
   ```
3. Запустить статический анализ PHPStan:
   ```bash
   ./vendor/bin/phpstan analyse
   ```
4. Выполнить сборку фронтенд-ресурсов:
   ```bash
   npm run build
   ```
5. В случае обнаружения предупреждений `E_DEPRECATED` или несовместимостей в коде `app/` или `tests/` — внести минимально необходимые исправления.

### 2.4. WP4 — Governance & Docs Update

1. Обновить [`docs/spec/constitution.md`](file:///c:/OSPanel/home/admin/docs/spec/constitution.md):
   - Указать `PHP ^8.5` в Разделе 3 (Runtime Contract).
2. Обновить [`AGENTS.md`](file:///c:/OSPanel/home/admin/AGENTS.md):
   - Указать `PHP 8.5` в блоке QUICK RULES.

---

## 3. Затрагиваемые Файлы

#### [MODIFY] [Dockerfile](file:///c:/OSPanel/home/admin/Dockerfile)
#### [MODIFY] [composer.json](file:///c:/OSPanel/home/admin/composer.json)
#### [MODIFY] [AGENTS.md](file:///c:/OSPanel/home/admin/AGENTS.md)
#### [MODIFY] [constitution.md](file:///c:/OSPanel/home/admin/docs/spec/constitution.md)
#### [NEW] [README.md](file:///c:/OSPanel/home/admin/docs/spec/php-85-upgrade/README.md)
#### [NEW] [requirements.md](file:///c:/OSPanel/home/admin/docs/spec/php-85-upgrade/requirements.md)
#### [NEW] [design.md](file:///c:/OSPanel/home/admin/docs/spec/php-85-upgrade/design.md)
#### [NEW] [implementation-plan.md](file:///c:/OSPanel/home/admin/docs/spec/php-85-upgrade/implementation-plan.md)
#### [NEW] [verification.md](file:///c:/OSPanel/home/admin/docs/spec/php-85-upgrade/verification.md)

---

## 4. Стратегия Отката (Rollback Strategy)

В случае выявления критических несовместимостей среды исполнения PHP 8.5, которые невозможно оперативно устранить в рамках `WP3`:
1. Выполнить `git checkout` изменений в `Dockerfile`, `composer.json`, `composer.lock`.
2. Пересобрать Docker-контейнер с образом PHP 8.4.
3. Вернуть спецификации в состояние `PHP 8.4` до устранения блокеров.
