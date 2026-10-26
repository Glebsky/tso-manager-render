# Ежедневный подарок и переплётчик

## Ежедневное посещение

В конструкторе задач доступно действие «Ежедневный подарок». Его можно добавить отдельно либо шагом серии. Payload: `{"scope":"self"}`. Для серии разумно поставить этот шаг после входа/синхронизации аккаунта. Чужие острова не поддерживаются.

Обработчик запрашивает свежий список заданий и принимает только награду с mode=5, showRewardWindow=true, trigger type=13 и валидным парным UID. Обычные ежедневные/гильдейские/приключенческие задания не принимаются. Отсутствие ожидающего подарка — нормальный результат без отправки команды.

Клиентская документация связывает кнопку принятия с QUEST_TRIGGER100 / REWARD_BUTTON_OK1. Это подтверждает необходимость отправки команды для штатного принятия. Начисляет ли сервер подарок без этой кнопки, на живом аккаунте не проверялось. Сообщение об успехе означает подтверждённый переход состояния задания, а не независимую сверку каждого ресурса инвентаря.

## Переплётчик

Существующие задачи «Производство» для production_type=2 автоматически используют новый порядок:

1. Прочитать свежую зону; проверить здание, рецепт, ограничения и очередь.
2. Если книга ещё производится — ничего не отправлять. Истечение расчётного времени само по себе не означает готовность.
3. Если головной заказ готов (`producedItems >= amount > 0`) — отправить DELIVER_PRODUCTION141 с целым числом2.
4. Получить явный успешный ответ и заново прочитать зону.
5. Только если очередь достоверно пуста, повторно проверить условия и отправить новый заказ91.

Поддерживаются рецепты переплётчика из существующего каталога, включая Manuscript/Tome/Codex. Недостаток ресурсов для нового заказа не препятствует получению уже готовой книги, но после получения новый заказ всё равно проходит полную проверку.

## Защита от повторов и ограничения

- Оба действия используют общий lock одного аккаунта. Остальные игровые действия и ручной клиент этим lock не охватываются.
- После отправки команды ошибка/обрыв/неизвестный ответ не запускают автоматический повтор внутри обработчика или внешнего session retry. В логе будет неподтверждённый исход; перед ручным повтором проверьте игру.
- Повторный отдельный запуск задачи читает состояние заново; вечной дедупликации между запусками нет.
- Неизвестные/некорректные данные очереди и отсутствующий questPool не считаются пустым состоянием.
- Приёмочные проверки выполнялись офлайн, без отправки игровых команд.

## Проверки

Новые тесты: DailyLoginTaskSchedulingTest, RewardLifecycleTest, RewardCommandsEncodingTest, DailyLoginParserTest и tests/js/dailyLoginReward.test.mjs. AMF daily_login_* fixtures синтетические, без аккаунтов и секретов. Реальный парсер проверен также на существующих обезличенных fixtures производства и зоны.

## Результат действия в панели задач

- Недостаточно ресурсов / книга ещё производится / действие занято / подарка нет: шаг **пропущен**, причина сохраняется в `step_results.reason`, показывается в подсказке шага, уведомлении ручного запуска и `last_result`. Серия не сообщает общий OK, если все её шаги пропущены.
- Ошибка игры, недостоверные данные или неизвестный итог после отправки: шаг **failed** с описанием, не успешный OK. Не повторяйте вслепую неизвестный результат.
- Успешный результат хранит сообщение обработчика в `step_results.message` и сводке. Это также сохраняется при передаче серии другому worker.
- Существующие обработчики со строковым результатом продолжают работать. Для двух составных команд используется отдельный типизированный результат; адаптер переводит его в существующие skip/failure-контракты вне post-send catch.

## Сборка и рабочая очередь

`npm run build` и `npm run dev` сначала вызывают `php artisan tso:lang:export-frontend`. Переводы PHP автоматически попадают в frontend JSON RU/UK/EN. После обновления приложения обновите страницу; старая открытая вкладка может держать старый JS.

В этом исправлении рабочая очередь штатно получила `queue:restart`; прежний worker завершился. Новый worker запущен в профиле OSPanel `tso.local` с прежним списком очередей. Read-only job подтвердил правильную папку проекта, регистрацию daily handler, семантические результаты и Python/pyamf. Диагностический job удалён из application-кода после завершения, его исходник и результат сохранены в `.autopilot/.../qa`.

## Границы живой проверки после замечаний

Пользователь подтвердил, что для рукописи действительно не хватало ресурсов. Окно ежедневного подарка перестало появляться по наблюдению пользователя; независимая сверка начисления не выполнена. Два ограниченных чтения состояния сервером отклонены с кодом1012 (более новая игровая сессия), включая штатный bounded retry. Новые команды принятия или производства в ходе этой диагностики не отправлялись.

## Восстановление сессии — обновление 2026-10-24

Рабочая web-сессия переиспользуется существующим `TsoAuthService::ensureAuthenticated`, он не изменялся. Новый `GameSessionRecovery` используется только при восстановлении: общий reset web/AMF/cache, один forced login, refresh Account, без сброса Client ID. Планировщик после forced login подтверждает home zone1002; при persistent1012 повторяются только чтения зоны (максимум3), не игровые изменения. Синхронизация использует те же reset/login primitives и штатное следующее getZone1002; её общий бюджет включает начальный typed session rejection.

Занятость сессии, сетевой таймаут или слово session в тексте ошибки не запускают forced login в sync. CAPTCHA/2FA прекращают восстановление. Нет нового логина на последней попытке без возможности проверить результат. Неподтверждённые ответы зоны не превращаются в успех.

Финальные проверки:715 PHP passed,5063 assertions;7 JS passed; PHPStan level8 и build passed; Pint изменённых файлов passed. Полный Pint по-прежнему обнаруживает прежний стиль routes/web.php; файл не менялся. Старый sync-тест initial recovery теперь использует typed SessionExpiredException; отдельный regression test запрещает reset при generic network error.

Live read-only probe аккаунта1 после патча остался заблокирован1012; Client ID сохранён, команды подарков/производства не отправлялись. Это не подтверждение успешного live-восстановления. Подробности: `.autopilot/2026-10-24-admin-daily-bookbinder--wip/session-recovery-handoff.md`. Очередь получила graceful queue:restart; подтверждён новый worker PID27952 после загрузки исправления. Конкурирующий игровой клиент/процесс — возможная, но не доказанная причина persistent1012.


## Уточнение R06: игровая идентичность отдельно от web-токена — 2026-10-24

Эта запись заменяет прежнее предположение «Client ID нужно сохранять при любом1012». В клиентском references CLIENT_AUTHRANDOM выбирается при запуске игрового клиента; это не постоянный ID устройства. Контролируемый live A/B: прежний Client ID дал1012; новый Client ID на том же web-токене дал code0 и291 здание дважды. Forced login0; команды подарков/производства0. Рабочая новая идентичность сохранена.

- Обычный успешный запрос использует существующие web-токен и Client ID, без reset/login.
- Только подтверждённый1012 после обычного warm-up разрешает одну CAS-замену отвергнутого игрового Client ID. Web-токен не сбрасывается. Перед callback подтверждается домашняя зона1002.
- GameClientIdentity хранит общий ID для всех процессов. Начальное создание атомарно; обновление под существующим AMF mutex сравнивает ID со снимком перед отвергнутым запросом. Если другой процесс уже обновил ID, используется победитель, без второй замены. Сетевая операция не выполняется под CAS mutex. Внешний account transaction lock отличается от AMF mutex.
- При занятом mutex и неизменном ID восстановление останавливается; чужая блокировка не снимается. В sync ошибка оборачивается в AccountSyncException409 с SessionBusyException как причиной; последующие getZone не выполняются. Добавлен regression test.
- Только typed SessionExpired / code1005 допускает один forced web login. Бюджет web login отдельный от одной замены identity; после восстановления нужна достоверная зона. Неподтверждённые изменения игры не переотправляются.
- Cache сохраняет существующий30-дневный TTL; истечение/очистка cache создаёт новую игровую идентичность. Это не долговечный device ID. Значение0 допустимо исходной формулой клиента; новые ID выбираются1..2147483646.
- Opus5.5 проверил конкретный код (requested/response совпали). Замечание о busy recovery исправлено основным агентом. Это code review, не слепая приёмка.

Read-only acceptance текущего кода: code0,291 здание, подтверждённый questPool, ожидающих daily0; очередь переплётчика читается, заказов0. На нормальном успешном пути токен и Client ID не изменились; web login0, reward/production commands0. Это доказывает доступ к состоянию, но не фактическое начисление подарков/книг. Автоматическая ветка1012 покрыта offline tests; искусственный дополнительный live конфликт не создавался.

Evidence: identity-probe.log, identity-acceptance-read.log, identity-final-*.log и identity-final-review-job.* в текущем run. Предыдущие session-recovery-handoff.md и отчёты сохранены как история, не текущий результат.


## Identity recovery handoff 2026-10-24T15:23:51.287003+03:00
724 PHP /5144 assertions,7 JS,PHPStan/build/dirty Pint passed. Full Pint: old routes/web.php only. Same-token live A/B fixed persistent1012 through bounded CAS identity renewal without web login. Post-restart read-only zone/quests/bookbinder pass, pending daily0/book orders0, no reward/production writes. Worker22388 replaces27952 gracefully. Opus review completed; busy recovery fix and regression included. Details: identity-recovery-handoff.md. Neutral pause; no background coding/model jobs, normal queue/dashboard remain.


## Итоговое исправление приёмки — 2026-10-24 (актуальнее предыдущих записей)

### Цена и подтверждение переплётчика

Для production_type=2 проверяется текущая цена, а не нижняя граница каталога. Источник — `resources_vector[].producedAmount` соответствующей книги; выбирается максимальный порог `costTiers.threshold <= producedAmount`. Текущий запас книги `amount` не является счётчиком производства. Отсутствующие, неоднозначные или некорректные данные счётчика/цены блокируют новый заказ с причиной `resource_data_unavailable`.

Готовая книга по-прежнему сначала получается. После получения читается свежая зона и повторно вычисляется цена: получение может перевести книгу на следующий ценовой порог. Недостаток ресурсов/данных для нового заказа не препятствует получению готовой книги.

После ACK0 на новый заказ успех требует свежей достоверной очереди с соответствующими рецептом, amount и stacks. Проверяется до трёх чтений; команда91 не повторяется. Пустая/несовпадающая/недостоверная очередь означает неподтверждённый исход, а не OK. Перед отдельным повторным запуском такого случая следует проверить состояние игры.

### Семантика интерфейса и авторизация

UI опирается на сохранённые статусы шагов, а не на наличие старого текста OK: skipped/partial показываются предупреждением и причиной, failed — ошибкой. Обрабатываются массив и разреженный объект step_results. RU/UK/EN экспортируются штатной сборкой.

Рабочий web-токен переиспользуется. Неподтверждённый сетевой исход проверки токена, 429 и 5xx не доказывают истечение сессии и не запускают login. Подтверждённое отклонение токена сохраняет путь повторной авторизации. Разделение web expiry и1012 игровой identity остаётся прежним. AMF POST не следует HTTP redirect: исход считается неподтверждённым, вторая отправка запрещена.

### Живая проверка и её границы

- Неактивная разовая QA task30 через TaskExecutionService: ожидающего daily нет → skipped, причина сохранена. Нового фактического получения подарка этот запуск не доказывает.
- QA task31 до последнего исправления ошибочно вернула OK, хотя свежая очередь оставалась пустой. Этот исторический результат не переписан; повторная production-команда не отправлялась. Отдельное structural AMF чтение подтвердило пустые очереди, а не ошибку извлечения существующего заказа.
- На аккаунте1 Manuscript producedAmount67: цена Coin18, запас Coin16; прежняя проверка брала начальные Coin10. Это выявленный дефект локальной проверки стоимости, а не явный серверный код отказа.
- QA task32 после исправления: штатный исполнитель сохранил skipped с причиной нехватки ресурсов. В диагностике оба метода production gateway принудительно отключены; обращений к ним0. Web-токен и игровая identity не изменились.
- QA tasks30/31/32 неактивные разовые, пользовательские расписания не менялись. Доступной ожидающей награды/готовой книги и достаточных монет для нового заказа в этой проверке не было. Фактическое начисление или успешный новый заказ не заявляются.

Новые регрессии: BookbinderTierCostTest, lifecycle подтверждения очереди и перехода цены после получения, TsoAuthTransportUnknownTest, HttpTsoClientRedirectSafetyTest, tests/js/taskResultPresentation.test.mjs. Финальные результаты сборки/полных gates записываются в final-acceptance-handoff.md текущего run; ранние цифры выше — история предыдущих этапов.


## Final acceptance repair 2026-10-24T16:13:43.194025+03:00
767 PHP /5370 assertions,21 JS,PHPStan/build/dirty Pint passed on final code. Full Pint retains only old routes/web.php style issue; AGENTS/routes unchanged. Current bookbinder tier calculation, fresh post-ACK matching queue, semantic warning UI and unknown auth/redirect safety fixed. Independent production code review PASS, not blind/live acceptance. Live task32 guarded insufficient-resource skip persisted; no production gateway calls. Post-restart read: code0/291 buildings, pending daily0/books0; token/identity unchanged, login0, writes0. One normal worker2484 after graceful consolidation; same queues. Live successful collect/enqueue unavailable with no pending reward/book and Coin16<18. No fake commit/G4/done. See final-acceptance-handoff.md. Neutral pause, no coding/model executor.


## R07 specialist scalar regression 2026-10-24T21:41:17.170750+03:00
Root cause confirmed offline: legacy AMF bytes captured as success message -> JSON false -> PostgreSQL prepared binding0. Model payloads14/24/25 are numeric, actions absent. Fix: filter transport bytes only after validated success, keep safe semantic UTF8 text; UTF8-safe summary byte truncation; explicit JSON_THROW_ON_ERROR before bulk writes; validate sequences before auth/commands, typed422; UI no fake sequence step, damaged warning and Run/Edit guards. Offline HTTP test: binary successful command once, response200 and intact original actions; malformed scalar response422 before authentication. No live game commands or account login used. Existing schedules unchanged, reward/book live acceptance remains cancelled. Definitions14/24/25 could not be recovered from checked sources, not replaced by guesses/empty actions. Task-definition snapshot of29 rows saved before worker reload. Full proof and final gates in scalar-regression-handoff.md.
