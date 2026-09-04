# Задачи (чек-лист исполнения)

> Часть спецификации **SPEC-001-session-reuse**. Навигация: [README](README.md) · [spec](spec.md) · [plan](plan.md) · [tasks](tasks.md) · [constitution](constitution.md)

Единая доска задач. Отмечай `- [x]` только после того, как гейты фазы прошли зелёными. Полный текст шага, дословные якоря кода и блоки замены — в документе фазы по ссылке в заголовке.

## Ф0. Инфраструктура кэша

Документ: [phases/phase-0-cache-infrastructure.md](phases/phase-0-cache-infrastructure.md)

- [ ] **Ф0.1.** Проверить фактические драйверы на всех процессах:
- [ ] **Ф0.2.** В `.env.example` заменить три строки (якоря дословные):
- [ ] **Ф0.3.** Проверить `docker-compose.yml`: у сервисов `app`, `worker`, `scheduler` в `environment` должны присутствовать `CACHE_DRIVER=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`, `REDIS_HOST=redis`.
- [ ] **Ф0.4.** Добавить защитное предупреждение при загрузке приложения. Файл `app/Providers/TsoServiceProvider.php`.
- [ ] **Ф0.5.** Гейты [quickstart.md](quickstart.md) зелёные. Коммит: `chore(config): default to shared redis cache and warn on per-process stores`.

## Ф1. Удаление отладочных дампов

Документ: [phases/phase-1-remove-debug-dumps.md](phases/phase-1-remove-debug-dumps.md)

- [ ] **Ф1.1.** Удалить строку (якорь дословный):
- [ ] **Ф1.2.** Удалить конструкцию (якорь дословный, обратите внимание на перенос строки внутри вызова):
- [ ] **Ф1.3.** Метод после правки должен выглядеть ровно так (кроме docblock, который не меняется):
- [ ] **Ф1.4.** Убрать ставший ненужным импорт. Сначала проверить, что `Storage` больше не используется в файле:
- [ ] **Ф1.5.** Удалить артефакты, оставшиеся на диске, и убедиться, что каталог не в git:
- [ ] **Ф1.6.** Гейты [quickstart.md](quickstart.md) зелёные. Коммит: `fix(auth): stop dumping play page and flash vars to storage`.

## Ф2. Типизированное исключение

Документ: [phases/phase-2-session-expired-exception.md](phases/phase-2-session-expired-exception.md)

- [ ] **Ф2.1.** Создать файл `app/Exceptions/SessionExpiredException.php`:
- [ ] **Ф2.2.** Гейты [quickstart.md](quickstart.md) зелёные. Коммит: `feat(exceptions): add SessionExpiredException`.

## Ф3. verifySession + ensureAuthenticated

Документ: [phases/phase-3-verify-session.md](phases/phase-3-verify-session.md)

- [ ] **Ф3.1.** Добавить константы сразу **после** закрывающей скобки константы `SERVERS` (якорь — строка `];` и следующая за ней пустая строка перед docblock метода `getCookieFile`):
- [ ] **Ф3.2.** DRY: в приватном методе `curlRequest()` заменить локальный массив заголовков на константу. Якорь:
- [ ] **Ф3.3.** Добавить методы `verifySession()` и `ensureAuthenticated()` **непосредственно перед** существующим методом `isAuthenticated()` (якорь — его docblock `/**\n     * Check if the account already has valid tokens.\n     */`):
- [ ] **Ф3.4.** Сбрасывать флаг при сбросе сессии. В методе `resetSession()` якорь:
- [ ] **Ф3.5.** Дедупликация одновременных логинов. Метод `login()` — якорь:
- [ ] **Ф3.6.** В конце `performLogin()`, сразу **после** блока `$account->update([...])` (якорь — строка `'status' => 'online',` и закрывающая `]);`) и **до** `return $params;` добавить:
- [ ] **Ф3.7.** Гейты [quickstart.md](quickstart.md) зелёные. Коммит: `feat(auth): reuse live sessions via cheap authenticate probe`.

## Ф4. Проверка ответа Load Server

Документ: [phases/phase-4-load-server-check.md](phases/phase-4-load-server-check.md)

- [ ] **Ф4.1.** Добавить импорт:
- [ ] **Ф4.2.** В методе `resolveServerUrl()` после логирования результата аутентификации. Якорь:
- [ ] **Ф4.3.** Файл `app/Services/TsoAmfService.php`, метод `sendServerCall()`. Якорь:
- [ ] **Ф4.4.** Гейты [quickstart.md](quickstart.md) зелёные. Коммит: `fix(amf): detect rejected sessions on load server authenticate`.

## Ф5. Скользящий TTL

Документ: [phases/phase-5-sliding-ttl.md](phases/phase-5-sliding-ttl.md)

- [ ] **Ф5.1.** В методе `dispatchCommand()` заменить финальный блок. Якорь (дословно, включая `return $response;`):
- [ ] **Ф5.2.** `config/game.php` — поднять значение по умолчанию. Якорь:
- [ ] **Ф5.3.** `.env.example` — добавить строку в блок `# TSO Game Settings`, сразу после `TSO_HTTP_TIMEOUT=30`:
- [ ] **Ф5.4.** Гейты [quickstart.md](quickstart.md) зелёные. Коммит: `fix(amf): keep active game sessions alive with a sliding ttl`.

## Ф6. Память о рабочем флоу

Документ: [phases/phase-6-remember-flow.md](phases/phase-6-remember-flow.md)

- [ ] **Ф6.1.** Добавить константу к остальным (рядом с `SESSION_OK_KEY`):
- [ ] **Ф6.2.** Добавить два приватных хелпера (рядом с `sessionParams()`):
- [ ] **Ф6.3.** Заменить в `performLogin()` весь блок попыток логина. Якорь — от `try {` с вызовом `loginLegacy` до `Cache::forget($cooldownKey);` включительно:
- [ ] **Ф6.4.** Гейты [quickstart.md](quickstart.md) зелёные. Коммит: `perf(auth): remember the login flow that worked per account`.

## Ф7. DRY во всех вызывающих

Документ: [phases/phase-7-dry-callers.md](phases/phase-7-dry-callers.md)

- [ ] **Ф7.1.** `app/Services/AccountService.php`, метод `executeAction()` (внутри `try`).
- [ ] **Ф7.2.** `app/Services/AccountService.php`, метод `getFriendZone()` (внутри `try`, отступ глубже — 16 пробелов).
- [ ] **Ф7.3.** `app/Services/TaskExecutionService.php`, метод `execute()`.
- [ ] **Ф7.4.** `app/Services/Tasks/Execution/SequenceStepExecutor.php`, метод `executeSingleStep()`.
- [ ] **Ф7.5.** `app/Services/Account/Sync/AccountSyncFetcher.php` — вызов **вне** блоков обработки `1005`/`1012` (тот, что предшествует циклу попыток). Вызовы `resetSession()` + `login()` внутри обработчиков ошибок **не трогать**.
- [ ] **Ф7.6.** `app/Services/Market/Sync/MarketOfferFetcher.php`, метод `fetch()` — первый вызов в начале метода. Обработчики `1005`/`1012` ниже **не трогать**.
- [ ] **Ф7.7.** Проверить, что не осталось прикладных вызовов старого шаблона:
- [ ] **Ф7.8.** Гейты [quickstart.md](quickstart.md) зелёные. Коммит: `refactor(auth): funnel all call sites through ensureAuthenticated`.

## Ф8. Тесты

Документ: [phases/phase-8-tests.md](phases/phase-8-tests.md)

- [ ] **Ф8.1.** Найти все моки старого метода:
- [ ] **Ф8.2.** `tests/Feature/SessionReuseTest.php` — покрыть ровно эти сценарии (имена тестов сохранить):
- [ ] **Ф8.3.** `tests/Feature/AuthDebugDumpRemovedTest.php` — регрессия на Ф1:
- [ ] **Ф8.4.** Прогнать полный набор и убедиться, что **ни один** ранее зелёный тест не покраснел:
- [ ] **Ф8.5.** Коммит: `test(session): cover session reuse and debug dump removal`.

## Ф9. Финальная приёмка

Документ: [quickstart.md](quickstart.md) и [acceptance.md](acceptance.md)

- [ ] `php artisan test` — зелёный, число тестов не уменьшилось.
- [ ] `vendor/bin/pint --test` — без нарушений.
- [ ] `vendor/bin/phpstan analyse` (или `composer phpstan`) — без новых ошибок. Новую ошибку **нельзя** прятать в `phpstan-baseline.neon`; её нужно исправить.
- [ ] `npm run build` — только если менялся фронтенд. В этой спеке фронтенд не меняется, поэтому шаг пропускается осознанно.
- [ ] `git diff --stat` сверен с [plan.md](plan.md): изменённых файлов не больше, чем в карте.
- [ ] Выполнить две задачи по одному аккаунту подряд с интервалом ~1 минута.
- [ ] В логах второй задачи присутствует `[TsoAmf] Reusing shared game session for account #...`.
- [ ] В логах второй задачи **отсутствуют** `[TsoAuth] legacy login failed`, `[TsoAuth] oauth login failed` и повторный полный логин.
- [ ] `[TsoAuth] Session probe for account #...: HTTP 200, alive=yes` встречается вместо полного логина.
- [ ] Ключи в Redis существуют и переиспользуются:
- [ ] Каталог `storage/app/debug/` не создаётся заново после логина.
- [ ] Файл `storage/app/cookies/account_{id}.txt` существует, права каталога `0700`, `mtime` обновляется пробой.
- [ ] Количество ошибок `1005`/`1012` в `bot_logs` за сутки заметно меньше, чем до изменений.
- [ ] Все чекбоксы Ф0–Ф8 отмечены.
- [ ] Таблица статусов в [constitution.md](constitution.md) обновлена (все фазы ✅).
- [ ] Гейты [quickstart.md](quickstart.md) зелёные на финальном коммите.
- [ ] Ручная проверка [quickstart.md](quickstart.md) пройдена.
- [ ] Инварианты [constitution.md](constitution.md) не нарушены; [research.md](research.md) соблюдён (ничего лишнего не сделано).
- [ ] `git diff --stat` соответствует [plan.md](plan.md).
- [ ] Журнал [deviations.md](deviations.md) заполнен (или пуст, если расхождений не было).
