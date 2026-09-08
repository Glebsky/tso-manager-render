# План реализации

> Часть спецификации **SPEC-001-session-reuse**. Навигация: [README](README.md) · [spec](spec.md) · [plan](plan.md) · [tasks](tasks.md) · [constitution](constitution.md)

## Порядок исполнения

Фазы выполняются строго по порядку. Каждая фаза = один коммит. После каждой фазы прогоняются гейты из [quickstart.md](quickstart.md).

| # | Фаза | Документ | Суть |
| --- | --- | --- | --- |
| 0 | Ф0 | [phases/phase-0-cache-infrastructure.md](phases/phase-0-cache-infrastructure.md) | Общий кэш между процессами |
| 1 | Ф1 | [phases/phase-1-remove-debug-dumps.md](phases/phase-1-remove-debug-dumps.md) | Удаление отладочных дампов |
| 2 | Ф2 | [phases/phase-2-session-expired-exception.md](phases/phase-2-session-expired-exception.md) | Типизированное исключение |
| 3 | Ф3 | [phases/phase-3-verify-session.md](phases/phase-3-verify-session.md) | verifySession + ensureAuthenticated |
| 4 | Ф4 | [phases/phase-4-load-server-check.md](phases/phase-4-load-server-check.md) | Честная проверка ответа Load Server |
| 5 | Ф5 | [phases/phase-5-sliding-ttl.md](phases/phase-5-sliding-ttl.md) | Скользящий TTL игровой сессии |
| 6 | Ф6 | [phases/phase-6-remember-flow.md](phases/phase-6-remember-flow.md) | Память о рабочем флоу логина |
| 7 | Ф7 | [phases/phase-7-dry-callers.md](phases/phase-7-dry-callers.md) | DRY — единая точка входа |
| 8 | Ф8 | [phases/phase-8-tests.md](phases/phase-8-tests.md) | Тесты |
| 9 | Ф9 | [quickstart.md](quickstart.md) | Финальная приёмка |

Модель данных (ключи кэша, TTL, переменные окружения): [data-model.md](data-model.md).
Контракты новых и изменяемых интерфейсов: [contracts/](contracts/).
Откат: [rollback.md](rollback.md). Журнал расхождений: [deviations.md](deviations.md).

## 4. Карта изменяемых файлов

| Файл | Фаза | Характер правки |
|---|---|---|
| `.env.example` | Ф0, Ф5 | правка 3 строк + 1 новая |
| `docker-compose.yml` | Ф0 | только проверка (правок не ожидается) |
| `app/Providers/TsoServiceProvider.php` | Ф0 | +1 приватный метод, тело `boot()` |
| `app/Services/TsoAuthService.php` | Ф1, Ф3, Ф6 | удаление дампов, +4 метода, +2 константы, переписан `performLogin()` |
| `app/Exceptions/SessionExpiredException.php` | Ф2 | **новый файл** |
| `app/Services/Amf/Transport/HttpTsoClient.php` | Ф4, Ф5 | +1 приватный метод, 3 точки правки |
| `app/Services/TsoAmfService.php` | Ф4, Ф7 | обработка нового исключения в `sendServerCall()` |
| `app/Services/AccountService.php` | Ф7 | 2 точки |
| `app/Services/TaskExecutionService.php` | Ф7 | 1 точка |
| `app/Services/Tasks/Execution/SequenceStepExecutor.php` | Ф7 | 1 точка |
| `app/Services/Account/Sync/AccountSyncFetcher.php` | Ф7 | 1 точка |
| `app/Services/Market/Sync/MarketOfferFetcher.php` | Ф7 | 1 точка |
| `config/game.php` | Ф5 | значение по умолчанию + комментарий |
| `tests/Feature/SessionReuseTest.php` | Ф8 | **новый файл** |
| `tests/Feature/AuthDebugDumpRemovedTest.php` | Ф8 | **новый файл** |
| существующие тесты, мокающие `isAuthenticated` | Ф8 | обновление моков |
| `docs/spec/session-reuse/spec.md` | Ф9 | этот документ |

Файлы, которые **нельзя** трогать: `app/Services/Amf/Amf3Encoder.php`, `app/Services/Amf/Vo/**`, `app/Services/ZoneParserService.php`, `app/Models/Account.php`, `database/migrations/**`, `resources/**`, `.env`.

---

