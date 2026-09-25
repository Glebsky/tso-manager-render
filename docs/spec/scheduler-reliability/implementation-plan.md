# Implementation Plan

Каждый WP: **сначала падающий тест** (характеризует дефект), затем минимальный фикс, затем gates:
`php artisan test`, `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse`, `npm run build`.
Один WP = одна ветка `duo/fix/scheduler-<wp>` = один MR. WP упорядочены по соотношению риск/эффект.

## Progress tracker

Статусы: ☐ not started · 🔄 in progress · ✅ done (код + тесты + gates) · ⏸ blocked.
Обновляется агентом после каждого WP; детали проверок — в `verification.md`.

| WP | Status | Date | Notes |
| :--- | :--- | :--- | :--- |
| WP-01 | ✅ | 2026-09-25 | `config('game.queues')` — единый источник; Docker `worker --queue=${TSO_WORKER_QUEUES:-…}`, `pcntl`; `--work` implied в `cron`; README «Deployment Modes». Тесты: `QueueTopologyTest`, `SchedulerModeTest` |
| WP-02 | ✅ | 2026-09-25 | `retry_after=200` (redis+database), `ExecuteScheduledTaskJob`: `tries=1`, `timeout=150`, `failOnTimeout`; `failed()` проверяет токен. Тесты: `QueueTimeoutInvariantTest`, `ExecuteScheduledTaskJobFailedTest` |
| WP-03 | ✅ | 2026-09-25 | `Cancelled` в `RESERVABLE_STATUSES` (`TaskSchedulerEngine`); `stop()`: `last_run_at=now()` только если задача была busy, once → `is_active=false`. Тест: `CancelledTaskReschedulingTest` (6) |
| WP-04 | ✅ | 2026-09-25 | `TaskStateMachine` с CAS переходов (`reserve`/`start`/`progress`/`finish`/`cancel`/`failJob`/`recoverStale`); «Run now» на busy возвращает 409; `TaskStateWriter` удален. Тесты: `TaskStateMachineTest` (12), 553 теста зеленые |
| WP-05 | ✅ | 2026-09-25 | Миграция `heartbeat_at`; `TaskStateMachine::heartbeat()` (CAS, `until` для hand-off); `reserve/start/progress` обновляют heartbeat; `DelayWaiter` (общее ожидание с heartbeat ≤ `game.scheduler.heartbeat_interval_seconds`, замечает Stop) в job и inline; `recoverStaleTasks()` читает `game.stale_task_timeout_minutes`, once → `failed`+`is_active=false`. Тесты: `TaskHeartbeatTest` (9), `ExecuteScheduledTaskJobHandOffTest` (2) |
| WP-06 | ✅ | 2026-09-25 | Реализовано по дефолтам A-1…A-3 из ADR-002 (см. примечание в `decisions.md`). Миграция `next_run_at` + индекс `(is_active, next_run_at)` + backfill; `Tasks\Scheduling\ScheduleCalculator` (чистый); `processDueTasks()` — индексный запрос без `payload`, self-heal `next_run_at IS NULL`, пропуск слотов CAS-ом (`skipMissedSlot`); `finish/cancel/failJob/recoverStale` пишут `next_run_at`; `create/update/toggle` пересчитывают. Тесты: `ScheduleCalculatorTest` (25), `NextRunAtSchedulingTest` (13), `NextRunAtBackfillMigrationTest` (1) |
| WP-07 | ✅ | 2026-09-25 | `SessionBusyException`; `HttpTsoClient::sendCommand()` и `TsoAuthService::login()` больше не работают без лока (`LockTimeoutException` → `SessionBusyException`); TTL лока — `game.session_lock_ttl_seconds` (по умолчанию `http_timeout*4+10`); `ActionRetryPolicy` ждёт `session_busy_retry_delay` и повторяет без relogin. Тесты: `SessionLockTest` (6) |
| WP-08 | ☐ | | Требует ручной проверки V-11 до merge |
| WP-09 | ☐ | | |
| WP-10 | ☐ | | |
| WP-11 | ☐ | | |

## Фаза 1 — Quick wins (конфигурация, без изменения домена)

### WP-01 — Воркер и очереди в обоих режимах (A-01, A-08) — FR-001, NFR-004, ADR-004 ✅
- Files: `docker-compose.yml`, `Dockerfile`, `routes/console.php`, `config/game.php`,
  `app/Console/Commands/RunSchedulerCommand.php`, `README.md`.
- Steps:
  1. `config('game.queues')` дополнить `accounts`, `default`; `RunSchedulerCommand` берёт список оттуда.
  2. Docker: `worker --queue=${TSO_WORKER_QUEUES:-tso-tasks,tso-accounts,tso-market,default}`; `pcntl` в образе.
  3. `routes/console.php`: `--work` только при `scheduler_mode=cron`.
  4. `README.md`: раздел «Режимы развёртывания» (Docker / OSPanel + Windows Task Scheduler) с ограничениями.
- Tests: `QueueTopologyTest` (все `onQueue()` ∈ конфиг; дефолт в compose = конфиг);
  `SchedulerModeTest` (`cron` → воркер запускается, `queue` → нет).

### WP-02 — `retry_after` / `timeout` / `tries` (A-02, A-09) — FR-002 ✅
- Files: `config/queue.php`, `app/Jobs/ExecuteScheduledTaskJob.php`.
- Test: `QueueTimeoutInvariantTest` — для каждой джобы `retry_after > timeout + 30`.
- Rollback: вернуть значения конфигурации.
- Примечание по реализации: с `tries=1` очередь вызывает `failed()` сразу после исключения,
  поэтому `failed()` теперь проверяет `execution_token` и не перезаписывает уже финализированный
  или перехваченный другим запуском статус (`ExecuteScheduledTaskJobFailedTest`). Полный CAS — в WP-04.

## Фаза 2 — Корректность состояний

### WP-03 — `Cancelled` не терминален (A-03) — FR-003, ADR-001 ✅
- Files: `TaskSchedulerEngine::processDueTasks/reserveAndDispatchTask`, `ScheduledTaskService::stop`.
- Steps: добавить `Cancelled` в резервируемые статусы; `stop()` фиксирует `last_run_at = now()`
  (окончание запуска, ADR-003); для once — `is_active=false`.
- Test: interval-задача `cancelled` + due → резервируется; остановленная в T → следующий запуск не раньше T + interval; once `cancelled` → нет.
- Примечание по реализации: `last_run_at` обновляется только если задача в момент stop была
  `queued|running` — stop «простаивающей» задачи (например `completed`) не сдвигает расписание,
  т.к. никакого запуска не завершилось. `stop()` по-прежнему не делает CAS — это WP-04.

### WP-04 — `TaskStateMachine` с CAS (A-04, A-05) — FR-004, FR-005, INV-001/002 ✅
1. Тесты гонок: stop между guard и start; `failed()` со старым токеном; поздний finish после stop; «Run now» на busy → 409.
2. Ввести `TaskStateMachine`; перевести `TaskStateWriter`, `ExecuteScheduledTaskJob`, `ScheduledTaskService::execute/stop`, `TaskSchedulerEngine` на него.
3. «Run now» использует `reserve()` (единый путь с планировщиком, параметр `force`).
4. Удалить `TaskStateWriter`.

### WP-05 — Heartbeat и stale recovery (A-11) — FR-007 ✅
- Миграция `heartbeat_at`; `SequenceRunner`/delay-ожидание вызывают `heartbeat()`.
- `recoverStaleTasks()` читает конфиг; once → `failed`.
- Примечание по реализации: ожидание вынесено в `Tasks\Execution\DelayWaiter` (используется job'ом и
  `executeInlineSequence`; в WP-09 его переиспользует `SequenceRunner`). Stale-условие —
  `heartbeat_at <= threshold`, для строк без heartbeat (до миграции) fallback на `queued_at`.
  Hand-off на отложенную джобу делает `heartbeat(until = now + delay)` CAS-ом: если run остановлен —
  повторный dispatch не делается. `start()` больше не трогает `queued_at` (это момент резервации).

## Фаза 3 — Расписание

### WP-06 — `next_run_at` + `ScheduleCalculator` + пропуск слотов (A-10) — FR-006, FR-010, NFR-001, ADR-002/003
- Миграция `next_run_at` + индекс `(is_active, next_run_at)` + backfill.
- Конфиг `game.scheduler.missed_grace_minutes` (env `TSO_MISSED_GRACE_MINUTES`, дефолт 5).
- Таблица unit-тестов `ScheduleCalculator` (UTC; интервал от окончания с точностью до секунды;
  daily после окончания; пропуск после простоя 3 дня = 0 запусков; граница окна grace ±1 с).
- `processDueTasks()` → индексный запрос без `payload`; пропущенные слоты сдвигаются CAS-ом.
- `ScheduledTaskService::create/update/toggle` пересчитывают `next_run_at`.
- ~~Blocked by: подтверждение предположений A-1…A-3~~ — реализовано по дефолтам ADR-002; изменение любого
  из них локализовано (см. `decisions.md`, «Статус предположений»).
- Примечание по реализации:
  - `ScheduleCalculator::nextRunAt(task, from)` — слот строго после `from` (используется при окончании
    запуска: `finish`, `cancel` busy, `failJob`, `recoverStale`); `initialRunAt(task, now)` — слот,
    выведенный из legacy-полей (backfill, self-heal); `scheduleRunAt(task, now, grace)` — для действий
    оператора (`create/update/toggle on`): просроченный слот сразу заменяется на будущий, чтобы действие
    пользователя не порождало предупреждение «missed slot».
  - `isTaskDue()` оставлен как публичный предикат «слот достигнут» (`next_run_at <= now`); решение
    «выполнить или пропустить» принимает `processDueTasks()` по окну grace.
  - Строки без `next_run_at` (созданы вне сервиса) лечатся в тике: слот вычисляется и пишется
    CAS-ом `WHERE next_run_at IS NULL`.
  - `duplicate()` не копирует `next_run_at` — копия неактивна, слот назначит `toggle()`.
  - API: `ScheduledTaskResource` отдаёт `next_run_at` (ISO-8601); frontend не менялся.

## Фаза 4 — Игровая сессия (главный кандидат на «1012 / нестабильно»)

### WP-07 — AMF-лок без «proceed anyway» (A-06) — FR-008, INV-004
- `SessionBusyException`; TTL лока; то же для login-лока.
- Test: второй процесс при занятом локе не вызывает транспорт (фейк `TsoClientInterface` / `Cache::lock` на array-store).

### WP-08 — Единая политика retry (A-07) — FR-009
- Убрать relogin из `TsoAmfService::sendServerCall()`; не сбрасывать client id в `invalidateSession()`; `ensureZoneLoaded()` через `GameResponseValidator`.
- Test: на одно действие ≤ 1 вызова `ensureAuthenticated(force: true)`.
- **Обязательная ручная проверка на живом аккаунте** (V-11) до merge.

## Фаза 5 — DRY/SRP (без изменения поведения)

### WP-09 — Единый движок sequence (A-12, B-11)
- `StepRunner` (на базе `StepOutcome`), `SequenceRunner`; job становится тонким адаптером.
- Удалить `executeInlineSequence`, pass-through методы `TaskExecutionService`.
- Характеризационные тесты `SequenceStepExecutor` должны пройти без изменений.

### WP-10 — Sync-джобы (A-13)
- `MarketSyncJob`: снимать lock только в `failed()` / после успешного `handle()`; `ShouldBeUnique` вместо ручного lock.
- `AccountSyncJob`: оставить только `ShouldBeUnique` (`uniqueFor` ≥ окна ретраев).
- Общий `UnrecoverableAuthError` — по типу исключения (`SessionExpiredException`, captcha-исключение), не по строке.
- `reserveAndDispatchTask()` sync-режим: `catch (Throwable)`.

### WP-11 — Архитектурная гигиена (B-01…B-10)
- `ZoneCacheInvalidator` интерфейс (B-03); общий curl/HTTP helper + удаление `curl_close()` (B-01, B-02);
  enum'ы статусов аккаунта/маркета (B-05); i18n для `(копия)` (B-08); `stopAllTasks` также отменяет busy (B-09);
  понизить уровень детальных `[TsoAmf]` логов до `debug` (B-10); удалить `TaskStatus::Paused` если не нужен (B-07).
- Каждый пункт — отдельный коммит.

## Порядок и зависимости

```
WP-01 ─┐
WP-02 ─┼─> WP-03 ─> WP-04 ─> WP-05 ─> WP-06
       │                 └─> WP-09
       └─> WP-07 ─> WP-08
WP-10, WP-11 — независимы, после WP-04
```

Рекомендуемый первый шаг: **WP-01 + WP-02** (минимальный риск, наибольший эффект на стабильность).
