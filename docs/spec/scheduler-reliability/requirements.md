# Requirements

## Functional

### FR-001 — Воркер обрабатывает все очереди приложения (ADR-004)

Оба режима развёртывания MUST обрабатывать `tso-tasks,tso-accounts,tso-market,default`.
Имена очередей MUST браться из одного места (`config('game.queues')`).

- Given Docker (`scheduler_mode=queue`), when задача зарезервирована, then долгоживущий `worker` начинает её ≤ 5 с.
- Given OSPanel (`scheduler_mode=cron`), when `tso:run-scheduler --work`, then встроенный воркер обрабатывает те же очереди, что и Docker-воркер.
- Given любой режим, then `README.md` описывает запуск и ограничения (нет `pcntl` на Windows).

### FR-002 — Одна задача выполняется не более чем одним процессом

- Given выполняющаяся задача дольше `retry_after`, then очередь MUST NOT выдать джобу повторно.
- Given `retry_after`, then `retry_after > job.timeout + safety_margin` — проверяется тестом конфигурации.

### FR-003 — Stop прерывает текущий запуск, но не расписание (ADR-001)

- Given interval/daily-задача в `cancelled`, when наступил следующий слот и `is_active=true`, then задача резервируется.
- Given interval-задача остановлена в момент T, then `next_run_at = T + interval` (Stop = окончание запуска, ADR-003).
- Given once-задача, when stop, then она не будет выполнена повторно автоматически (`is_active=false`).

### FR-004 — Ручной запуск не ломает текущий

- Given задача `queued|running`, when «Run now», then HTTP 409 (`error.task_busy`), состояние задачи не меняется.
- Given задача не занята, when «Run now», then резервация через тот же атомарный путь, что и у планировщика.

### FR-005 — Все переходы состояния — CAS по `execution_token`

- Given запись результата/прогресса/heartbeat, then `UPDATE … WHERE id=? AND execution_token=?`; 0 строк → процесс прекращает работу и ничего не пишет.
- Given `failed()` джобы с устаревшим токеном, then состояние задачи не меняется.

### FR-006 — Детерминированное расписание (`next_run_at`)

- Задача хранит `next_run_at` (UTC), вычисляемый единым `ScheduleCalculator`.
- Выборка due-задач — индексный SQL `WHERE is_active AND next_run_at <= now AND status NOT IN (queued, running)`, без загрузки `payload`.
- Interval (ADR-003): `next_run_at = finished_at + interval`, точность — секунды (без округления до минут).
- Daily: следующий `run_at_time` строго после момента окончания.
- Once: `next_run_at = null` после резервации.
- Создание / изменение расписания / включение toggle: `next_run_at` пересчитывается
  (interval: `last_run_at + interval`, если `last_run_at` есть, иначе `now + interval`).

### FR-010 — Пропущенные слоты пропускаются (ADR-002)

- Given `now - next_run_at <= missed_grace_minutes`, then задача резервируется как обычно.
- Given `now - next_run_at > missed_grace_minutes`, then задача **не** выполняется; `next_run_at` сдвигается на следующий будущий слот; `last_result = [Skipped] missed slot …`; activity log `warning`.
- Given пропущенная daily-задача после простоя 3 дня, then выполняется 0 раз и ставится на ближайшее будущее `run_at_time`.
- Given пропущенная once-задача, then она не выполняется, `is_active=false`, `status=failed` (предположение A-2).
- Given `missed_grace_minutes` не задан, then используется `5`.

### FR-007 — Heartbeat и stale recovery

- Выполняющийся процесс обновляет `heartbeat_at` минимум раз в `heartbeat_interval` (≤ 30 с), включая ожидание delay.
- Stale recovery использует `config('game.stale_task_timeout_minutes')` и `heartbeat_at`.
- Восстановленная once-задача переходит в `failed` (не в `pending`), чтобы не выполнить побочные эффекты дважды.

### FR-008 — Сериализация доступа к игровой сессии

- Если per-account AMF-лок не получен за `session_lock_wait_seconds`, команда MUST NOT отправляться; бросается `SessionBusyException` (retryable, без relogin).
- TTL лока ≥ худшего времени операции под локом (resolve + команда).

### FR-009 — Единая политика retry/relogin

- Relogin выполняется только в одном слое (`ActionRetryPolicy`), не более 1 раза на действие.
- Client id не сбрасывается при invalidate сессии (только при смене учётных данных).
- Детекция 1012 — по разобранному AMF-ответу (`GameResponseValidator`), не по `str_contains`.

## Non-functional

- **NFR-001**: Тик планировщика с 1 000 активных задач ≤ 200 мс на выборку (индекс, без загрузки `payload`).
- **NFR-002**: Все изменения схемы — аддитивные, с backfill; откат миграции не теряет данных.
- **NFR-003**: Gates из `AGENTS.md` зелёные после каждого WP.
- **NFR-004**: Docker-образ содержит `pcntl`. Режим OSPanel/cron поддерживается как равноправный (ADR-004); ограничение «`$timeout` без `pcntl` не применяется» задокументировано и компенсировано `http_timeout` + бюджетом джобы.

## Invariants

- **INV-001**: `status ∈ {queued, running}` ⇔ `execution_token IS NOT NULL`.
- **INV-002**: В любой момент не более одного процесса с правом записи в задачу (владеет текущим токеном).
- **INV-003**: `is_active=true ∧ schedule ∈ {daily, interval}` ⇒ `next_run_at IS NOT NULL`.
- **INV-004**: Для одного аккаунта не более одной AMF-команды в полёте.

## Observability

- **OBS-001**: Каждый переход статуса логируется одной строкой: `task_id, from, to, token_prefix, reason`.
- **OBS-002**: Потерянный CAS (0 строк) — `warning` с `task_id` и причиной.
- **OBS-003**: Метрики Pulse: задержка старта (`started_at - next_run_at`), число stale recovery, число 1012.

## Out of scope

- Мультипользовательность (ADR-001 в силе).
- Horizon (может быть отдельной спецификацией).
