# Обязательный SDD-процесс

## Phase 0 — Baseline

1. Выполнить `git status --short`.
2. Записать существующие незакоммиченные изменения; не присваивать их себе.
3. Запустить baseline quality gates.
4. Зафиксировать уже существующие падения отдельно от новой работы.
5. Определить scope и non-goals.

## Phase 1 — Research

Исполнитель обязан установить текущее поведение по коду:

- маршруты и middleware;
- controller → application service → domain/service → infrastructure flow;
- модели, casts, таблицы, индексы и транзакции;
- jobs, locks, retry и idempotency;
- внешние HTTP/AMF/Python интеграции;
- observability, redaction и error mapping;
- существующие unit, feature и integration tests.

Каждый факт сопровождается путём к файлу и именем класса/метода. Неизвестные факты записываются как open questions; их нельзя заменять догадками.

## Phase 2 — Requirements

Для каждого поведения создаются IDs:

- `FR-*` — функциональные требования;
- `NFR-*` — нефункциональные требования;
- `INV-*` — неизменяемые инварианты;
- `SEC-*` — требования безопасности;
- `OBS-*` — наблюдаемость;
- `COMP-*` — совместимость.

Каждое требование содержит измеримые acceptance criteria и негативные сценарии. Запрещены слова «улучшить», «оптимально», «при необходимости» без точного результата.

## Phase 3 — Design

До кода определить:

1. Текущий и целевой flow.
2. Ответственность компонентов.
3. Направление зависимостей.
4. Transaction boundary.
5. Locking, retry и idempotency.
6. Ошибки и их преобразование в HTTP/API.
7. Backward compatibility.
8. Migration и rollback.
9. Отклонённые варианты и причины.

## Phase 4 — Contracts

Описать все изменяемые границы:

- HTTP request/response;
- service method;
- DTO;
- database schema;
- event/job payload;
- external transport;
- cache key и TTL;
- log/metric/event.

Контракт должен описывать вход, выход, ошибки, side effects, security, idempotency и compatibility.

## Phase 5 — Characterization tests

Перед рефакторингом зафиксировать существующее поведение тестами. Characterization test не утверждает, что поведение идеально; он защищает от случайного изменения.

## Phase 6 — Atomic implementation

- Одна задача — один проверяемый результат.
- Сначала failing test, затем минимальный production code.
- После каждого шага запускается узкая проверка.
- Максимум 5–7 связанных production-файлов на задачу, если план явно не объясняет большее число.
- Не выполнять opportunistic cleanup.

## Phase 7 — Failure and security paths

Проверить validation, authentication, authorization, timeout, transport failure, retry exhaustion, race condition, replay, duplicate request, malformed external response и secret leakage.

## Phase 8 — Full verification

Запустить все quality gates из `04-quality-gates.md` и сопоставить каждый requirement с доказательством.

## Phase 9 — Report

Создать `implementation-report.md` со списком изменённых файлов, реализованных требований, результатов проверок, известных ограничений и rollback-инструкций.
