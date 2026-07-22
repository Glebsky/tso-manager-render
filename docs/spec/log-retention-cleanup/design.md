# Проектирование: Log Retention Policy Cleanup Service

## 1. Архитектура компонента

```
                               ┌─────────────────────────┐
                               │  tso:run-scheduler      │
                               │  (RunSchedulerCommand)  │
                               └────────────┬────────────┘
                                            │
                                  calls processAutoCleanup()
                                            │
                                            ▼
                               ┌─────────────────────────┐
 ┌───────────────────────────┐ │ SystemLogCleanupService │
 │ SettingsController        ├─┤                         │
 │ (DELETE /api/settings/logs)│ │ - cleanExpiredLogs()    │
 └───────────────────────────┘ │ - clearAllLogs()        │
                               └────────────┬────────────┘
                                            │
                                   queries & deletes
                                            │
                                            ▼
                               ┌─────────────────────────┐
                               │ Model: BotLog (DB)      │
                               └─────────────────────────┘
```

## 2. Интерфейс `SystemLogCleanupService`

```php
namespace App\Services;

use App\Models\BotLog;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class SystemLogCleanupService
{
    public function shouldRunCleanup(Carbon $now): bool;
    public function cleanExpiredLogs(Carbon $now): int;
    public function clearAllLogs(): void;
    public function processAutoCleanup(Carbon $now): bool;
}
```

## 3. Алгоритм проверки и выполнения

1. `processAutoCleanup(Carbon $now)`:
   - Проверяет `shouldRunCleanup($now)`.
   - Если `false` -> возвращает `false` (пропуск).
   - Если `true` -> в блоке `try-catch` вызывает `cleanExpiredLogs($now)`.
   - При успехе: обновляет `Setting::set('last_log_cleanup_at', $now->toIso8601String())`, возвращает `true`.
   - При ошибке: пишет `Log::error(...)`, swallows exception, возвращает `false`.

2. `shouldRunCleanup(Carbon $now)`:
   - `$days = (int) Setting::get('log_retention_days', 30);`
   - Если `$days <= 0`, вернуть `false`.
   - `$lastRun = Setting::get('last_log_cleanup_at');`
   - Если `$lastRun` отсутствует, вернуть `true`.
   - Вернуть `$now->diffInHours(Carbon::parse($lastRun)) >= 24`.
