# Модель данных: ключи кэша, TTL, конфигурация

> Часть спецификации **SPEC-001-session-reuse**. Навигация: [README](README.md) · [spec](spec.md) · [plan](plan.md) · [tasks](tasks.md) · [constitution](constitution.md)

Этот документ — единственный источник правды по именам ключей, форматам значений и TTL. Если в документе фазы имя ключа отличается от указанного здесь — **это дефект спецификации**, остановись и запиши расхождение в [deviations.md](deviations.md). Не придумывай ключи сам.

## 1. Persistent-хранилище

Новых таблиц и миграций в этой спецификации **нет**. Ни одно поле модели `Account` не добавляется и не переименовывается. Всё новое состояние живёт в кэше (Redis) и переживает перезапуск процессов, но осознанно является эфемерным: потеря кэша означает лишь повторный логин, а не потерю данных.

## 2. Ключи кэша

### 2.1. Существующие (НЕ менять формат)

| Ключ | Значение | TTL | Владелец |
| --- | --- | --- | --- |
| `tso:amf_session:{account}:{generation}:{zone}` | Массив: DSId, URL сервера, `resolved_at` | `game.session_ttl_seconds` | `HttpTsoClient` |
| `tso:amf_gen:{account}` | int, номер поколения сессии | без TTL | `HttpTsoClient` |
| `tso:amf_lock:{account}` | lock | короткий | `HttpTsoClient` |
| `tso:login_lock:{account}` | lock, ожидание `->block(25)` | короткий | `TsoAuthService::login()` |
| `tso:client_id:{account}` | string, AMF client id | 30 дней | `TsoAmfService::clientIdFor()` |
| `account_login_cooldown:{account}` | timestamp кулдауна после неудачи | 900 c | `TsoAuthService::performLogin()` |

Изменение формата любого из этих ключей запрещено: при выкате новой версии рядом со старой разъезд форматов приведёт к тому, что процессы перестанут видеть чужие сессии.

### 2.2. Новые ключи

| Ключ | Константа | Тип значения | TTL | Вводится в |
| --- | --- | --- | --- | --- |
| `tso:session_ok:{account}` | `TsoAuthService::SESSION_OK_KEY` | `true` (маркер) | `SESSION_OK_TTL` = 300 c | [Ф3](phases/phase-3-verify-session.md) |
| `tso:auth_flow:{account}` | `TsoAuthService::AUTH_FLOW_KEY` | string: `legacy` \| `cip` \| `oauth` | 30 дней | [Ф6](phases/phase-6-remember-flow.md) |

Правила:

- Префикс ключа задаётся константой класса и **всегда** конкатенируется с `$account->id`. Никакой интерполяции имени аккаунта, e-mail или сервера в ключ.
- `tso:session_ok:` — это подтверждение «сессия была жива не более 5 минут назад», а не «пользователь залогинен». Единственный писатель — `markSessionVerified()`, единственный стиратель — `forgetSessionVerified()`, который обязан вызываться из `resetSession()`.
- `tso:auth_flow:` хранит только имя удачного флоу. Значение вне перечисленных трёх считается мусором и игнорируется: используется дефолтный порядок.

## 3. Файловое состояние

| Путь | Назначение | Права | Изменяется |
| --- | --- | --- | --- |
| `storage/app/cookies/account_{id}.txt` | cookie-банка cURL, по одной на аккаунт | `0700` | нет |
| `storage/app/debug/play_page.html` | отладочный дамп | — | **удаляется** в [Ф1](phases/phase-1-remove-debug-dumps.md) |
| `storage/app/debug/flash_vars.json` | отладочный дамп с токеном | — | **удаляется** в [Ф1](phases/phase-1-remove-debug-dumps.md) |

Изоляция по аккаунтам обеспечивается именем cookie-файла и наличием `account.id` во всех ключах кэша. Любая правка, которая уводит cookie-файл в общий путь, нарушает инвариант изоляции.

## 4. Конфигурация

### 4.1. `config/game.php`

| Ключ | Было | Становится | Фаза |
| --- | --- | --- | --- |
| `session_ttl_seconds` | `(int) env('TSO_SESSION_TTL', 300)` | `(int) env('TSO_SESSION_TTL', 1800)` | [Ф5](phases/phase-5-sliding-ttl.md) |
| `session_lock_wait_seconds` | `env('TSO_SESSION_LOCK_WAIT', 20)` | без изменений | — |

Семантика `session_ttl_seconds` меняется с «время жизни от момента логина» на «время бездействия»: после каждого успешного HTTP 200 поле `resolved_at` обновляется и сессия перезаписывается в кэш.

### 4.2. `.env.example`

Правится **только** `.env.example`. Файл `.env` на рабочих машинах агент не трогает.

| Переменная | Было | Становится | Фаза |
| --- | --- | --- | --- |
| `CACHE_DRIVER` | `file` | `redis` | [Ф0](phases/phase-0-cache-infrastructure.md) |
| `QUEUE_CONNECTION` | `sync` | `redis` | [Ф0](phases/phase-0-cache-infrastructure.md) |
| `SESSION_DRIVER` | `file` | `redis` | [Ф0](phases/phase-0-cache-infrastructure.md) |
| `TSO_SESSION_TTL` | отсутствует | `1800` (в блоке `# TSO Game Settings`) | [Ф5](phases/phase-5-sliding-ttl.md) |

### 4.3. Тестовое окружение

`phpunit.xml` задаёт `CACHE_DRIVER=array`. Это допустимый непригодный для шаринга стор, поэтому предупреждение из `TsoServiceProvider::boot()` обязано глушиться гвардом `app()->runningUnitTests()`. Иначе прогон тестов зальёт лог шумом.

## 5. Жизненный цикл сессии

1. Вызывающий код обращается к `ensureAuthenticated($account)`.
2. Есть `tso:session_ok:{id}` → считаем сессию живой, ничего не делаем.
3. Нет флага → `verifySession()` делает дешёвую проверку. Успех → `markSessionVerified()`.
4. Проверка провалилась → `login()`; под локом повторно проверяем флаг, чтобы параллельные воркеры не логинились дважды.
5. `performLogin()` начинается с `resetSession()` (чистая cookie-банка), заканчивается `markSessionVerified()` и `rememberFlow()`.
6. Игровой слой берёт AMF-сессию из `tso:amf_session:...`; при каждом успешном ответе TTL продлевается.
7. Сервер отверг сессию → `SessionExpiredException` → `resetSession()` + `login()` + `invalidateSession()` (сдвиг поколения) → **один** повтор запроса.
