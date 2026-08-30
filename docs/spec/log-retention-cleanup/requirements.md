# Требования: Автоматическая очистка логов по Log Retention Policy в Cron

## 1. Цель
Обеспечить авто-очистку устаревших системных логов (`BotLog`) в планировщике `tso:run-scheduler` на основании имеющейся настройки `log_retention_days` из базы данных (`Setting`).

## 2. Функциональные требования

### FR-1: Использование Log Retention Policy из БД
- Значение периода хранения читается из настройки `log_retention_days` (`Setting::get('log_retention_days', 30)`).
- Если `log_retention_days <= 0` (значение 0 = "хранить бессрочно"), автоочистка пропускается.
- Если `log_retention_days > 0`, удаляются записи `BotLog`, у которых `created_at < now()->subDays($retentionDays)`.

### FR-2: Контроль интервала запуска (Last Execution Tracking)
- Cron запускается ежеминутно, но тяжелая операция очистки должна выполняться не чаще 1 раза в сутки (интервал 24 часа).
- Время последнего запуска сохраняется в настройке `last_log_cleanup_at` в ISO-8601 формате (`Setting::set('last_log_cleanup_at', $now->toIso8601String())`).
- Автоочистка выполняется только если `last_log_cleanup_at` отсутствует (null) либо с последнего запуска прошло 24 часа и более.

### FR-3: Переиспользование бизнес-логики Clear System Logs
- Вся логика определения системных логов (`BotLog`) выносится в единый сервис `SystemLogCleanupService`.
- Ручная очистка (`SettingsController::clearLogs`) и автоочистка в Cron используют методы данного сервиса.

### FR-4: Изоляция ошибок
- Сбой или исключение во время автоочистки логов записывается в журнал Laravel (`Log::error(...)`).
- Ошибка очистки логов не должна приводить к сбою или остановке выполнения остальных задач `RunSchedulerCommand`.

### FR-5: Сохранение логов Laravel (`storage/logs/*.log`)
- Очистка затрагивает только системные логи приложения в БД (`BotLog`).
- Файлы логов Laravel в `storage/logs/*.log` не затрагиваются и не удаляются.

## 3. Критерии приемки (Acceptance Criteria)
1. При вызове `tso:run-scheduler` система проверяет `log_retention_days` и `last_log_cleanup_at`.
2. Если срок наступил, удаляются только записи `BotLog` старше `log_retention_days`.
3. После выполнения обновляется `last_log_cleanup_at`.
4. Ручная очистка через API (`DELETE /api/settings/logs`) по-прежнему удаляет логи и работает через `SystemLogCleanupService`.
5. Все тесты (`php artisan test`) и линтер (`./vendor/bin/pint`) успешно проходят.
