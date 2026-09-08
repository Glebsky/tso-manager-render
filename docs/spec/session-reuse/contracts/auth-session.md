# Контракт: сессия и аутентификация

> Часть спецификации **SPEC-001-session-reuse**. Навигация: [README](../README.md) · [spec](../spec.md) · [plan](../plan.md) · [tasks](../tasks.md) · [constitution](../constitution.md)

Здесь зафиксированы сигнатуры и поведенческие обязательства. Реализация с точным кодом — в документах фаз. Если реализация расходится с контрактом — побеждает контракт, расхождение фиксируется в [deviations.md](../deviations.md).

## 1. `TsoAuthService` — публичная поверхность

```php
// Новое. Единственная точка входа для всего вызывающего кода.
// Гарантирует живую сессию либо бросает исключение. Ничего не возвращает.
public function ensureAuthenticated(Account $account): void;

// Новое. Дешёвая проверка живости сессии без полного логина. Аналог FastAuth() эталона.
// НЕ бросает исключений: любая ошибка транспорта = false.
public function verifySession(Account $account): bool;

// Существующее. НЕ удалять и НЕ менять сигнатуру: используется UI и тестами.
public function isAuthenticated(Account $account): bool;
```

Приватные помощники, вводимые в [Ф3](../phases/phase-3-verify-session.md):

```php
private function markSessionVerified(Account $account): void;   // ставит tso:session_ok:{id} на 300 c
private function forgetSessionVerified(Account $account): void; // снимает флаг
private function preferredFlow(Account $account): ?string;      // Ф6
private function rememberFlow(Account $account, string $flow): void; // Ф6
```

### Обязательства `ensureAuthenticated()`

| # | Обязательство |
| --- | --- |
| C1 | Идемпотентность: повторный вызов при живой сессии не делает ни одного сетевого запроса. |
| C2 | При живой сессии не пишет в лог ничего на уровне выше `debug`. |
| C3 | Не логинится, если параллельный процесс уже вошёл: после взятия лока флаг проверяется повторно. |
| C4 | При невозможности войти пробрасывает существующее исключение авторизации. Новых типов ошибок наружу не добавляет. |
| C5 | Не глотает капчу и 2FA: текущее поведение (`2fa_required`, кулдаун) сохраняется дословно. |

### Обязательства `verifySession()`

| # | Обязательство |
| --- | --- |
| V1 | Возвращает `bool`, никогда не бросает. |
| V2 | Не модифицирует cookie-файл и не вызывает `resetSession()`. |
| V3 | Использует те же заголовки браузера, что и остальные запросы (константа `BROWSER_HEADERS`). |
| V4 | При `true` вызывающий обязан вызвать `markSessionVerified()`; сама функция флаг не ставит. |

## 2. `SessionExpiredException`

```php
final class SessionExpiredException extends \RuntimeException {}
```

| # | Обязательство |
| --- | --- |
| E1 | Наследуется от `RuntimeException`, **не** от `TaskExecutionException`: это не ошибка выполнения задачи, а сигнал транспортного слоя о необходимости переавторизации. |
| E2 | Класс `final`. Наследники запрещены. |
| E3 | Бросается только транспортом (`HttpTsoClient`). Ловится только в `TsoAmfService::sendServerCall()`. |
| E4 | Никогда не доходит до HTTP-ответа API: если долетело до контроллера — это дефект. |

## 3. Транспорт: распознавание отвергнутой сессии

```php
private function isSessionRejected(int $status, string $body): bool;
```

| # | Обязательство |
| --- | --- |
| T1 | Возвращает `true` для тела с маркером `ERROR`, для статусов `401`, `403` и для любого `3xx`. |
| T2 | Не использует сравнение подстрок текста исключения. Старая логика `str_contains($errorMsg, 'Load Server')` и подобные удаляются полностью. |
| T3 | Не меняет строки `User-Agent` и `Referer`: они должны совпадать с эталоном байт в байт. |
| T4 | Не трогает кодирование AMF, VO и `ZoneParser`. |

## 4. Политика повтора

| # | Обязательство |
| --- | --- |
| R1 | На один вызов `sendServerCall()` допускается **ровно один** повтор после переавторизации. |
| R2 | Последовательность восстановления строго: `resetSession()` → `login()` → `invalidateSession()` → повтор. |
| R3 | Второй `SessionExpiredException` пробрасывается наружу без повтора. |
| R4 | Повтор не сбрасывает и не удлиняет кулдаун `account_login_cooldown:{id}`. |

## 5. Контракт вызывающего кода (Ф7)

Во всех шести точках вызова блок вида

```php
if (! $this->authService->isAuthenticated($account)) { /* ... */ }
```

заменяется на

```php
$this->authService->ensureAuthenticated($account);
```

Точки: `AccountService::executeAction`, `AccountService::getFriendZone`, `TaskExecutionService::execute`, `SequenceStepExecutor::executeSingleStep`, `AccountSyncFetcher`, `MarketOfferFetcher::fetch`.

После замены обязателен контрольный `grep -rn 'isAuthenticated' app/`: остаться должны только объявление метода и его использование внутри `TsoAuthService`. Любое другое вхождение — незавершённая фаза.
