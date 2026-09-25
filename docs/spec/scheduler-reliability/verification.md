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
| V-06 | FR-006 | Unit: `ScheduleCalculator` таблица кейсов; Feature: индексный due-запрос | ☐ |
| V-07 | FR-007 | Feature: heartbeat во время delay; once stale → failed | ☐ |
| V-08 | FR-008 | Unit: занятый лок → `SessionBusyException`, транспорт не вызван | ☐ |
| V-09 | FR-009 | Unit: ≤ 1 forced relogin на действие | ☐ |
| V-10 | NFR-001 | Бенчмарк тика на 1 000 задач (`load-tests/`) | ☐ |
| V-11 | FR-009 | **Ручная**: 24 ч на живом аккаунте, число 1012 в логах до/после | ☐ |
| V-12 | NFR-003 | Все gates после каждого WP | ✅ WP-01+02: test 535 passed / pint / phpstan No errors / build ok (2026-09-25) · ✅ WP-03: test 541 passed / pint / phpstan No errors / build ok (2026-09-25) · ✅ WP-04: test 553 passed / pint / phpstan No errors / build ok (2026-09-25) |
| V-13 | FR-010, ADR-002 | Unit: пропуск слота вне окна grace → 0 запусков, `next_run_at` в будущем; внутри окна → запуск | ☐ |
| V-14 | ADR-003 | Unit: interval 5 мин, окончание 12:03:17 → `next_run_at` 12:08:17 | ☐ |
