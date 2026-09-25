# Audit — evidence-based findings

Условные обозначения: **[C]** подтверждено запуском, **[R]** подтверждено чтением кода,
**[H]** гипотеза, требует проверки на живом сервере.

## A. Планировщик задач

### A-01 — Docker `worker` не слушает очереди `tso-*` [R] — Critical

- `docker-compose.yml` → `worker.command: php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600`
  без `--queue`, значит слушается только `default` (`config/queue.php` → `REDIS_QUEUE` = `default`).
- Все джобы уходят в `tso-tasks` / `tso-accounts` / `tso-market`
  (`ExecuteScheduledTaskJob`, `AccountSyncJob`, `MarketSyncJob` → `onQueue(...)`).
- Фактически задачи выполняет только inline-воркер из `tso:run-scheduler --work`
  (раз в минуту, `--max-time=50 --max-jobs=20`).
- **Симптомы**: ручной «Run now» ждёт до 60 с; отложенная передача шага sequence
  срабатывает только на следующем тике; при >20 джобах в минуту образуется хвост;
  при долгом шаге inline-воркер занимает минутный слот, а `withoutOverlapping(10)` пропускает тики.
- `routes/console.php` передаёт `--work` **всегда**, независимо от `scheduler_mode`. После исправления
  Docker-воркера в Docker будут работать два воркера на одних очередях — лишний источник гонок.
  Флаг должен зависеть от режима (ADR-004).

### A-02 — `retry_after` < `timeout` → повторная доставка [R] — Critical

- `config/queue.php` → `redis.retry_after = 90`; `ExecuteScheduledTaskJob::$timeout = 120`.
  Для `database` это уже исправлено (180), для `redis` — нет.
- Цикл джобы: бюджет 45 с + длительность последнего шага. Один шаг может включать
  `resolveServerUrl()` (до 20 × (HTTP 30 с + sleep 2 с)), `ensureZoneLoaded()`, `sleep()` в
  `ActionRetryPolicy`. 90 с легко превышаются.
- Через 90 с Redis отдаёт ту же джобу второму воркеру → **два процесса выполняют одну задачу
  с одним токеном**; токен-проверка не спасает, т.к. токен совпадает. Итог — двойные игровые
  действия и конкурентные AMF-сессии.

### A-03 — `Cancelled` терминален для периодических задач [C] — Critical

- `ScheduledTaskService::stop()` ставит `status = cancelled`.
- `TaskSchedulerEngine::processDueTasks()` / `reserveAndDispatchTask()` выбирают только
  `pending|completed|failed`; `recoverStaleTasks()` берёт только `queued|running`.
- Пробный тест: interval-задача в `cancelled` с просроченным слотом → `dispatched count=0`.
- **Симптом**: «остановил задачу — она больше никогда не запускается, хотя включена».
- Парадокс: если single-action джоба завершится *после* stop, `markCompleted()` перезапишет
  `cancelled → completed`, и задача «оживёт» (см. A-05). Поведение недетерминировано.

### A-04 — «Run now» перетирает выполняющуюся задачу [C] — High

- `ScheduledTaskService::execute()` безусловно пишет `queued` + новый токен + `completed_steps = 0`,
  без проверки `isBusy()` (контроллер проверяет busy только *после*).
- Пробный тест: задача `running` c `completed_steps=3` → после ручного запуска `queued`, токен
  заменён, прогресс обнулён.
- Для single-action задачи старый процесс по завершении пишет `completed` и `token=null` →
  новая джоба видит `completed` и пропускается («instead of 'queued'») → **ручной запуск
  тихо теряется**.

### A-05 — Переходы состояний без compare-and-set [R] — High

- Резервация атомарна (`UPDATE … WHERE status IN (…)`), но все последующие записи —
  `TaskStateWriter::markRunning/markCompleted/markFailed/markSequenceFinished/updateStepProgress`,
  `ExecuteScheduledTaskJob::failed()`, `stop()` — безусловные `$task->update()`.
- Гонки:
  - `stop()` между guard и `markRunning()` → `running` с `token=null` → висит до stale recovery.
  - `failed()` старой джобы перезаписывает статус новой (нет проверки токена).
  - Любой «опоздавший» процесс перезаписывает `payload.step_results` актуального запуска.

### A-06 — Таймаут AMF-лока молча игнорируется [R] — High

- `HttpTsoClient::sendCommand()`: `$lock->block(20)` бросает `LockTimeoutException`,
  которое ловится `catch (Throwable)` → «Proceeding without session lock» → команда идёт
  **параллельно** с держателем лока. Ровно тот сценарий, от которого лок защищает (1012).
- Аналогично `TsoAuthService::login()` (`block(25)`).
- TTL лока 60 с меньше худшего времени `resolveServerUrl()` → лок истекает посреди операции.

### A-07 — Вложенные retry/relogin и сброс client id [R]/[H] — High

- Слой 1: `TsoAmfService::sendServerCall()` на `SessionExpiredException` делает forced relogin.
- Слой 2: `ActionRetryPolicy::execute()` на 1005/1012 делает `resetSession` + `invalidateSession`
  + forced relogin, до 3 попыток.
- Итого до ~6 логинов на одно действие; каждый логин создаёт новую игровую сессию.
- `TsoAmfService::invalidateSession()` вызывает `forgetClientId()`, хотя docblock
  `forgetClientId()` говорит «only needed when credentials change». Новый client id сам по себе
  делает клиента «новым» для сервера. **[H]**: вероятный усилитель каскада 1012.
- `ensureZoneLoaded()` детектирует 1012 через `str_contains($binaryAmf, '1012')` —
  ложные срабатывания на любых данных, содержащих «1012» (grid, id, количества).

### A-08 — Нет `pcntl` в Docker-образе [R] — High

- `Dockerfile` не ставит `pcntl`; без него `queue:work` не может принудительно убить джобу
  по `$timeout`. Зависший вызов держит воркер, пока не истечёт `http_timeout`/не завершится.
- Локально (Windows/OSPanel) `pcntl` недоступен в принципе → timeout не работает никогда.

### A-09 — `tries/backoff` фактически не работают [R] — Medium

- Non-sequence: `TaskExecutionService::execute()` при ошибке пишет `failed`, `token=null` и
  пробрасывает исключение. Повтор (attempt 2) видит `failed` и выходит → `failed()` не вызывается.
- Настройки `$tries = 3`, `$backoff = [30,120,300]` вводят в заблуждение.

### A-10 — Округление интервала [R] — Low (переклассифицирован по ADR-003)

- `isIntervalTaskDue()`: база = `last_run_at`, который пишется **по окончании** выполнения.
  По ADR-003 это **желаемое** поведение, дрейф на длительность выполнения — не дефект.
- Остаётся дефектом: `diffInMinutes()` → `(int)` + `abs()` округляет вниз, из-за чего
  запуск сдвигается до +1 минуты (тик) сверх интервала.
- Нет обработки пропущенных слотов: после простоя daily-задача догоняет слот, что
  противоречит ADR-002.
- Каждую минуту грузятся все активные задачи с полным `payload` и оцениваются в PHP.

### A-11 — Stale recovery [R] — Medium

- `config('game.stale_task_timeout_minutes')` нигде не читается; `recoverStaleTasks()` вызывается
  с дефолтом 10.
- Heartbeat (`queued_at`) обновляется только при резервации, `markRunning()` и hand-off.
  В sync-режиме `executeInlineSequence()` делает `sleep($delay)` без heartbeat → задача
  может быть «восстановлена» в `pending` во время выполнения и запущена второй раз.
- Восстановление переводит в `pending`, из-за чего once-задача с `last_run_at=null` будет
  выполнена повторно (возможен двойной эффект).

### A-12 — Два движка sequence и дублирование [R] — Medium (DRY/KISS)

- `SequenceStepExecutor::executeInlineSequence()` (sync-режим) и `executeSingleStep()` (queue)
  реализуют одну логику дважды; третья копия — `TaskExecutionService::executeSingleSequenceAction()`.
- `StepOutcome` / `StepResultCollection` определены, но нигде не используются (мёртвый код).
- `TaskExecutionService::executeSingleActionWithRetry()/executeSingleAction()` — pass-through.
- `ExecuteScheduledTaskJob::failed()` пишет `BotLog` напрямую, минуя `TaskActivityLogger`
  (и `CredentialRedactor`).

### A-13 — Lock'и синхронизаций [R] — Medium

- `MarketSyncJob::handle()` в `finally` снимает `market_sync_lock` **на каждой попытке**, даже
  если джоба будет повторена → следующий тик может поставить вторую синхронизацию того же сервера.
- `AccountSyncJob`: `ShouldBeUnique` + ручной cache-lock (300 с) — двойной механизм; TTL
  лока < суммарного окна ретраев (120 × 3 + 30 + 120 + 300).
- `isUnrecoverableAuthError()` реализован дважды и по-разному (DRY); строковый матчинг
  сообщений вместо типов исключений.
- `TaskSchedulerEngine::reserveAndDispatchTask()` в sync-режиме ловит `Exception`, а не
  `Throwable` → `TypeError`/`Error` прерывает весь цикл планировщика.

## B. Архитектура в целом

| # | Находка | Принцип | Severity |
| :--- | :--- | :--- | :--- |
| B-01 | `HttpTsoClient` (501 стр.) и `TsoAuthService` (585 стр.): сырой cURL скопирован ≥3 раз, заголовки/UA дублируются; нельзя использовать `Http::fake()` | SRP, DRY, тестируемость | Medium |
| B-02 | `curl_close()` deprecated в PHP 8.5 (no-op с 8.0) — шум в логах | Hygiene | Low |
| B-03 | Статические `ApplyBuffHandler::invalidate*ForAccount()` вызываются из `ActionRetryPolicy` и `TaskExecutionService` — скрытая связность инфраструктуры с конкретным handler | DIP, SRP | Medium |
| B-04 | Два `ZoneSnapshotProviderInterface` (Mines, Production) + два `ZoneSnapshot` (`Support\Zone`, `Game\Mines`) | DRY | Low |
| B-05 | Магические строки статусов аккаунта (`session_expired`) и маркета (`error`, `disabled`) | Type safety | Low |
| B-06 | Смешение `Cache::` фасада и инжектированного `CacheRepository` в одном слое | Consistency, DIP | Low |
| B-07 | `TaskStatus::Paused` не используется | Dead code | Low |
| B-08 | Хардкод русского текста в `ScheduledTaskService::duplicate()` (`(копия)`) | i18n | Low |
| B-09 | `SettingsController::stopAllTasks()` снимает только `is_active`, не прерывает запущенные; название вводит в заблуждение | Least surprise | Low |
| B-10 | Логи `[TsoAmf]` на каждую команду уровня `info` с кусками ответов → объём логов/BotLog; нет sampling | Observability cost | Low |
| B-11 | `ExecuteScheduledTaskJob::handle()` содержит оркестрацию sequence (цикл, бюджет, sleep, hand-off) — бизнес-логика в инфраструктурном классе | SRP | Medium |

## C. Не проверялось

- `docs/references` (файлы игры) — не анализировались, протокол не менялся.
- Поведение на живом игровом сервере (A-07 — гипотеза).
- Frontend-логика опроса статуса задачи.
