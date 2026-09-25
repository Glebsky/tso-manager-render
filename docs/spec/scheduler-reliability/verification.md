# Verification

Заполняется при исполнении. Статус: ☐ not run · ✅ passed · ❌ failed · ⚠ unverified (с причиной).

## Baseline (2026-09-25, до изменений)

| Check | Result |
| :--- | :--- |
| `php artisan test` | ✅ 523 passed |
| `./vendor/bin/pint --test` | ✅ |
| `./vendor/bin/phpstan analyse` | ✅ No errors |
| `npm run build` | ✅ |
| Probe: cancelled interval-задача перезапускается | ❌ `dispatched count=0` (A-03 подтверждён) → ✅ после WP-03 |
| Probe: «Run now» на running-задаче | ❌ токен перезаписан, `completed_steps 3 → 0` (A-04 подтверждён) |

## Matrix

| ID | Requirement | Check | Status |
| :--- | :--- | :--- | :--- |
| V-01 | FR-001, ADR-004 | `QueueTopologyTest`, `SchedulerModeTest`; Docker: `docker compose up` → задача стартует ≤ 5 с; OSPanel: `tso:run-scheduler --work` обрабатывает все очереди | ✅ автотесты (7 passed, 2026-09-25) · ⚠ ручная проверка Docker ≤ 5 с не выполнялась (нет Docker в среде агента) |
| V-02 | FR-002 | `QueueTimeoutInvariantTest` | ✅ 2026-09-25 (+ `ExecuteScheduledTaskJobFailedTest`, 3 passed) |
| V-03 | FR-003 | Feature: cancelled recurring → reserved | ✅ `CancelledTaskReschedulingTest` 6 passed (2026-09-25): interval/daily cancelled → reserved; inactive → нет; stop в T → due ровно в T+interval, не в T+4min; once stop → `is_active=false`; stop idle → `last_run_at` не тронут |
| V-04 | FR-004 | Feature: «Run now» на busy → 409, состояние не изменено | ✅ `TaskStateMachineTest::test_manual_execute_on_busy_task_throws_409_conflict` (2026-09-25) |
| V-05 | FR-005 | Unit: CAS-проигрыш не пишет в задачу (4 сценария гонок) | ✅ `TaskStateMachineTest` (12 passed, 2026-09-25): reserve, start, progress, finish, cancel, failJob, stale recovery |
| V-06 | FR-006 | Unit: `ScheduleCalculator` таблица кейсов; Feature: индексный due-запрос | ✅ `ScheduleCalculatorTest` 25 passed (2026-09-25); `NextRunAtSchedulingTest::test_due_selection_is_a_single_indexed_query_without_payload` — один `select` без `payload`, с `is_active = ?` и `next_run_at <= ?`; create/update/toggle пересчитывают слот; once → `next_run_at=null` после резервации; backfill — `NextRunAtBackfillMigrationTest` |
| V-07 | FR-007 | Feature: heartbeat во время delay; once stale → failed | ✅ `TaskHeartbeatTest` 9 passed (2026-09-25): heartbeat каждые ≤ interval в `DelayWaiter` (Sleep::fake, 35 с → ≥3 heartbeat), Stop во время ожидания прерывает, CAS по токену, конфиг `stale_task_timeout_minutes` читается, once stale → `failed`+inactive, recurring → `pending`; `ExecuteScheduledTaskJobHandOffTest` 2 passed: hand-off сдвигает heartbeat на now+delay, Stop до hand-off → без dispatch |
| V-08 | FR-008 | Unit: занятый лок → `SessionBusyException`, транспорт не вызван | ✅ `SessionLockTest` 6 passed (2026-09-25): amf-лок занят → `SessionBusyException`, транспорт не достигнут, чужой лок не украден; лок освобождается при ошибке транспорта; TTL ≥ 3×http_timeout+10; login-лок занят → `SessionBusyException`, `performLogin` не стартует; `ActionRetryPolicy`: busy → sleep 5 с → retry без `ensureAuthenticated`/`resetSession`/`invalidateSession`; после `max_action_attempts` — исключение наверх |
| V-09 | FR-009 | Unit: ≤ 1 forced relogin на действие | ✅ `RetryPolicyTest` 9 passed (2026-09-25): `SessionExpiredException` → ровно 1 `ensureAuthenticated(force:true)` + retry; persistent `SessionExpiredException`/1005/1012 при `max_action_attempts`=5–6 → relogin ровно 1, затем исключение (2–3 вызова действия, не 6); 1012 → warm-up без relogin; `TsoAmfService` пробрасывает `SessionExpiredException` без `ensureAuthenticated`; `invalidateSession` сохраняет `tso:client_id:*`; `ensureZoneLoaded` не реагирует на «1012» в данных (парсер → `errorCode 0` → 1 вызов, без sleep) и повторяет при декодированном 1012 |
| V-10 | NFR-001 | Бенчмарк тика на 1 000 задач (`load-tests/`) | ☐ |
| V-11 | FR-009 | **Ручная**: 24 ч на живом аккаунте, число 1012 в логах до/после | ⚠ не выполнена — у агента нет живого аккаунта. Требуется владельцу до merge WP-08: сравнить `grep -c "1012" storage/logs/laravel.log` за 24 ч до и после; отдельно убедиться, что сохранение client id при `invalidateSession()` не ломает восстановление сессии (если после relogin 1012 не проходит — вернуть `forgetClientId()` в `ActionRetryPolicy::relogin()`, не в `TsoAmfService`) |
| V-12 | NFR-003 | Все gates после каждого WP | ✅ WP-01+02: test 535 passed / pint / phpstan No errors / build ok (2026-09-25) · ✅ WP-03: test 541 passed / pint / phpstan No errors / build ok (2026-09-25) · ✅ WP-04: test 553 passed / pint / phpstan No errors / build ok (2026-09-25) · ✅ WP-05: test 564 passed / pint / phpstan No errors / build ok (2026-09-25) · ✅ WP-06: test 603 passed / pint / phpstan No errors / build ok (2026-09-25) · ✅ WP-07: test 609 passed / pint / phpstan No errors / build ok (2026-09-25) · ✅ WP-08: test 618 passed / pint / phpstan No errors / build ok (2026-09-25) · ✅ WP-09: test 627 passed / pint / phpstan No errors / build ok (2026-09-25) · ✅ WP-10: test 635 passed / pint / phpstan No errors / build ok (2026-09-25) |
| V-13 | FR-010, ADR-002 | Unit: пропуск слота вне окна grace → 0 запусков, `next_run_at` в будущем; внутри окна → запуск | ✅ (2026-09-25) `ScheduleCalculatorTest::test_is_missed_boundary` (граница 5:00 = вовремя, 5:01 = пропуск); `NextRunAtSchedulingTest`: 4 мин → reserved; 10 мин → 0 запусков, `next_run_at = now+interval`, `last_result` `SKIPPED: missed slot …`, BotLog warning; daily после 3 дней простоя → 0 запусков, ближайший будущий `run_at_time`; once → `failed`+inactive; CAS-сдвиг не двоится; grace по умолчанию 5 |
| V-14 | ADR-003 | Unit: interval 5 мин, окончание 12:03:17 → `next_run_at` 12:08:17 | ✅ `ScheduleCalculatorTest::test_interval_counts_from_the_end_of_the_run_with_second_precision` + `NextRunAtSchedulingTest::test_finish_sets_next_run_at_from_the_end_of_the_run_with_second_precision` (2026-09-25) |
