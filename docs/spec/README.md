# Спецификации TSO Manager

Индекс пакетов Spec-Driven Development. Статус пакета фиксируется в его `README.md`;
здесь — сводка на момент аудита документации (2026-09-26).

## Общие правила

| Документ | Назначение |
| --- | --- |
| [constitution.md](constitution.md) | Конституция проекта: runtime-контракт, слои, гейты. Имеет приоритет над пакетами. |
| [laravel-constitution.md](laravel-constitution.md) | Правила работы с Laravel 12, Pint и Artisan. |
| [security-refactor/](security-refactor/README.md) | Процесс SDD, правила исполнителя, quality gates и шаблоны пакетов. |

Текущий runtime: **PHP ^8.5, Laravel ^12.0, Vue 3**. Упоминания PHP 8.1–8.4 и
Laravel 10/11 в пакетах ниже описывают историю миграции и не являются действующими ограничениями.

## Форматы пакетов

- **SDD** — `README.md` + `requirements.md`, `design.md`, `implementation-plan.md`, `verification.md` (опционально `decisions.md`, `data-sources.md`, `pitfalls.md`, `audit.md`).
- **SpecKit** — `spec.md`, `plan.md`, `tasks.md`, `phases/` (только `session-reuse`).
- **Однофайловый** — вся спецификация в одном `README.md` или `plan.md` для небольших фич.
- **Патч** — исправления к другому пакету; при противоречии побеждает патч.

## Пакеты

### Архитектура и платформа

| Пакет | Формат | Статус |
| --- | --- | --- |
| [solid-refactor](solid-refactor/README.md) — SOLID и слоистая архитектура (Phase 1) | SDD | Implemented; продолжение — `architecture-hardening` |
| [architecture-hardening](architecture-hardening/README.md) — Phase 2, включая переход на PHP 8.4 / Laravel 12 | SDD | Implemented (все WP — DONE) |
| [scheduler-reliability](scheduler-reliability/README.md) — Phase 3, надёжность планировщика | SDD | Implemented (WP-01…WP-11); V-10, V-11 — ручная проверка |
| [php-85-upgrade](php-85-upgrade/README.md) — переход на PHP 8.5 | SDD | Implemented |
| [session-reuse](session-reuse/README.md) — переиспользование игровой сессии | SpecKit | Частично: чек-листы отмечены не полностью |
| [multi-level-cache](multi-level-cache/plan.md) — многоуровневый кэш | Однофайловый | Implemented |
| [log-retention-cleanup](log-retention-cleanup/README.md) — очистка системных логов | SDD (без `verification.md`) | Implemented |
| [lang-optimize](lang-optimize/README.md) — унификация локализации | SDD + `implementation-report.md` | Implemented |

### Планировщик задач

| Пакет | Формат | Статус |
| --- | --- | --- |
| [task-planner-multi-select](task-planner-multi-select/README.md) — множественный выбор зданий и специалистов | SDD | Implemented |
| [task-grouping](task-grouping/README.md) — группы задач и drag-and-drop | Однофайловый (as-built) | Implemented |
| [collect-pickups](collect-pickups/README.md) — сбор коллекций | Однофайловый | Implemented (`CollectPickupsHandler`) |
| [collect-building](collect-building/README.md) — сбор наград кликом по зданию | SDD | Implemented (`CollectBuildingHandler`); DoD-чек-лист не отмечен |
| [build-mine](build-mine/README.md) — постройка и апгрейд шахт | SDD | Implemented (`BuildMineHandler`); DoD-чек-лист не отмечен |
| [buff-production-queue](buff-production-queue/README.md) — очередь производства бафов | SDD | Implemented |
| [production-recipe-sources](production-recipe-sources/README.md) — источники рецептов производства | SDD | Implemented |
| [production-recipe-sources-v1.1](production-recipe-sources-v1.1/READ-FIRST.md) — обновление v1.1 | Патч к v1.0 | Implemented |

### Рынок

| Пакет | Формат | Статус |
| --- | --- | --- |
| [market-tradeables](market-tradeables/README.md) — приключения, бафы и постройки как лоты | SDD | Implemented |

## Правила сопровождения

- Новый пакет добавляется в этот индекс в том же коммите.
- Статус указывается одинаково во всех файлах пакета: `Draft` → `Approved` → `Implemented`.
- Ссылки — только относительные; `file:///` и абсолютные пути запрещены.
- Файлы — UTF-8 без BOM.
