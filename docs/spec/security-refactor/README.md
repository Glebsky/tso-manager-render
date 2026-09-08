# Spec-Driven Development Plan — TSO Manager Admin

Версия: 1.0  
Стек: PHP 8.5, Laravel 12, Vue 3, Vite, Tailwind, PostgreSQL, Redis  
Назначение: стандарт подготовки спецификаций и выполнения задач AI-моделями с минимальной автономностью.

## Главный принцип

Кодирование начинается только после утверждения требований, архитектуры, контрактов, атомарного плана и способов проверки.

Поток работы:

```text
Research → Requirements → Design → Contracts → Tasks → Implementation → Verification → Report
```

## Порядок чтения

1. `01-process.md` — обязательный процесс.
2. `02-architecture-rules.md` — правила PHP/Laravel и SOLID/DRY/KISS.
3. `03-executor-rules.md` — ограничения для AI-исполнителя.
4. `04-quality-gates.md` — тесты и критерии завершения.
5. `05-security-checklist.md` — security-by-design.
6. `templates/` — шаблоны новой спецификации.
7. `example-security-hardening/` — рекомендуемый первый пакет работ.

## Обязательные ограничения

- Перед изменением читать реальный код.
- Не изменять `.env`, секреты и production-данные.
- Не выполнять destructive-команды без явного подтверждения.
- Не анализировать и не редактировать `vendor/`, `node_modules/`, `storage/`, `bootstrap/cache/`, `public/build/`.
- Не смешивать feature, security fix, dependency update и несвязанный refactoring.
- Не менять публичный контракт без требования и migration plan.
- PostgreSQL определяет production semantics; SQLite-тест не доказывает корректность блокировок и concurrency.

## Definition of Done

Работа завершена только когда требования трассируются до тестов, все обязательные проверки проходят, security-риски рассмотрены, rollback описан, а фактические отклонения внесены в `deviations.md`.
