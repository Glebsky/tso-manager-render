# План реализации: Автоматическая очистка логов по Log Retention Policy

## Файлы изменений

### 1. [NEW] `app/Services/SystemLogCleanupService.php`
Создание сервиса для инкапсуляции логики очистки `BotLog`, проверки условий запуска по `log_retention_days` и `last_log_cleanup_at`, и обработки ошибок.

### 2. [MODIFY] `app/Http/Controllers/SettingsController.php`
Делегирование ручной очистки логов (`clearLogs()`) в `SystemLogCleanupService::clearAllLogs()`.

### 3. [MODIFY] `app/Console/Commands/RunSchedulerCommand.php`
Внедрение `SystemLogCleanupService` и добавление вызова `processAutoCleanup($now)` в цикл `handle()`.

### 4. [NEW] `tests/Feature/SystemLogCleanupTest.php`
Написание автоматических тестов:
- Очистка только устаревших логов при наступившем сроке;
- Пропуск автоочистки при `log_retention_days = 0`;
- Пропуск автоочистки, если с последнего запуска прошло менее 24 часов;
- Изоляция ошибок (сбой очистки не ломает выполнение команды);
- Ручная очистка всех логов через API.
