# Scheduler Reliability & Architecture Audit (Phase 3)

## Status

Implemented — WP-01…WP-11 завершены, автоматические gates зафиксированы в `verification.md`.
Остаются ручные проверки V-10 (нагрузочный benchmark) и V-11 (24 часа на живом аккаунте); до их выполнения спецификация считается **Implemented with manual verification pending**.
Точный прогресс и доказательства — в таблице **Progress tracker** в `implementation-plan.md` и матрице `verification.md`.

## Goal

Планировщик задач выполняет каждую задачу **ровно один раз в свой слот**, предсказуемо
восстанавливается после сбоев и не порождает конкурирующих игровых сессий (ошибка 1012).

## Non-goals

- Изменение AMF-протокола и VO-классов.
- Переписывание frontend (кроме отображения новых статусов, если потребуется).
- Изменение `.env` и секретов.
- Несвязанный рефакторинг маркета/аналитики.

## Documents

| Document | Purpose |
| :--- | :--- |
| `decisions.md` | **ADR-001…004 — решения владельца. Читать первым.** |
| `audit.md` | Находки с доказательствами (файл/метод, как воспроизвести, severity). |
| `requirements.md` | FR / NFR / INV с acceptance criteria. |
| `design.md` | Целевая модель состояний, CAS-переходы, `next_run_at`, единый движок sequence. |
| `implementation-plan.md` | Упорядоченные независимо поставляемые work packages (WP) с gates. |
| `verification.md` | Матрица проверок; заполняется при исполнении. |

## Baseline (2026-09-25)

| Gate | Result |
| :--- | :--- |
| `php artisan test` | 523 passed (4072 assertions) |
| `./vendor/bin/pint --test` | passed |
| `./vendor/bin/phpstan analyse` | No errors |
| `npm run build` | success |

Зелёные gates при этом **не ловят** найденные дефекты: сценарии конкурентности,
redelivery очереди и конфигурации Docker тестами не покрыты.

## Priority summary

| # | Theme | Severity |
| :--- | :--- | :--- |
| A-01 | Docker `worker` не слушает очереди `tso-*` | Critical |
| A-02 | Redis `retry_after` (90) < `ExecuteScheduledTaskJob::$timeout` (120) → повторная доставка, двойное выполнение | Critical |
| A-03 | `Cancelled` — терминальный статус: остановленная периодическая задача больше никогда не запускается | Critical |
| A-04 | Ручной «Run now» перетирает токен выполняющейся задачи | High |
| A-05 | Переходы состояний без compare-and-set (stop/finish/markRunning гонки) | High |
| A-06 | Таймаут AMF-лока игнорируется → параллельные сессии → 1012 | High |
| A-07 | Два вложенных слоя relogin/retry + сброс client id при каждом retry | High |
| A-08 | Нет `pcntl` в образе → `$timeout` джобы не применяется | High |
| A-09 | `tries/backoff` у `ExecuteScheduledTaskJob` фактически мёртвые | Medium |
| A-10 | Округление интервала до минут (дрейф от окончания — ожидаемое поведение, ADR-003) | Low |
| A-11 | Stale recovery: конфиг не используется, heartbeat отсутствует | Medium |
| A-12 | Два движка sequence + 3× дублирование обработки результата шага | Medium |
| A-13 | Дубли/гонки lock'ов в `MarketSyncJob` / `AccountSyncJob` | Medium |
| A-14 | Прочие замечания по архитектуре (см. `audit.md` §B) | Low–Medium |

## Decisions

OQ-001…OQ-004 закрыты 2026-09-25 — см. **`decisions.md`** (ADR-001…ADR-004).
Документ имеет приоритет над остальными.

| OQ | Решение |
| :--- | :--- |
| 001 | Stop прерывает только текущий запуск, расписание сохраняется |
| 002 | Пропущенные слоты пропускаются (окно допуска 5 мин) |
| 003 | Интервал — от окончания прошлого запуска |
| 004 | Поддерживаются оба варианта: Docker и OSPanel/cron |

Открытые предположения A-1…A-3 — в `decisions.md`.
