# Фаза Ф4. Честная проверка ответа Load Server

> Часть спецификации **SPEC-001-session-reuse**. Навигация: [README](../README.md) · [spec](../spec.md) · [plan](../plan.md) · [tasks](../tasks.md) · [constitution](../constitution.md)


Файл: `app/Services/Amf/Transport/HttpTsoClient.php`.

> Сейчас ответ `bb/authenticate` только логируется. Протухший токен уезжает в цикл `for ($i = 0; $i < 20; $i++) { ... sleep(2); }` — до ~40 секунд ожидания, 20 бесполезных запросов, и лишь потом невнятное `Timeout waiting for Load Server`. Эталон принимает решение именно по этому ответу.

- [ ] **Ф4.1.** Добавить импорт:

```php
use App\Exceptions\SessionExpiredException;
```

  Он должен стоять первым в блоке `use` (алфавитно `App\Exceptions\...` идёт перед `App\Models\Account`).

- [ ] **Ф4.2.** В методе `resolveServerUrl()` после логирования результата аутентификации. Якорь:

```php
        Log::info("[TsoAmf] Load server authentication: HTTP {$authStatus}, response: ".trim($authRes));
```

  Добавить **сразу после** этой строки:

```php

        if ($this->isSessionRejected($authStatus, $authRes)) {
            throw new SessionExpiredException(
                "Load server rejected the stored session for account #{$account->id} (HTTP {$authStatus})."
            );
        }

        if ($authStatus !== 200) {
            Log::warning("[TsoAmf] Unexpected load server authentication status for account #{$account->id}: HTTP {$authStatus}");
        }
```

  И добавить приватный метод (разместить непосредственно перед `resolveServerUrl()`, после метода `extractDsIdFromResponse()`):

```php
    /**
     * The reference client treats an "ERROR" body from bb/authenticate as a dead
     * session (client/login.xaml.cs, FastAuth). 401/403 and 3xx redirects to the
     * login page mean the same thing.
     *
     * Everything else (500, 502, timeouts) is transient and must NOT trigger a
     * re-login: a re-login would create yet another game session.
     */
    private function isSessionRejected(int $status, string $body): bool
    {
        if (str_contains($body, 'ERROR')) {
            return true;
        }

        return in_array($status, [401, 403], true) || ($status >= 300 && $status < 400);
    }
```

  Затем заменить существующий бросок при 3xx от Load Server. Якорь:

```php
            if ($lsStatus >= 300 && $lsStatus < 400) {
                throw new \RuntimeException("Load Server returned status {$lsStatus}: Session expired or invalid.");
            }
```

  Заменить на:

```php
            if ($lsStatus >= 300 && $lsStatus < 400) {
                throw new SessionExpiredException("Load Server returned status {$lsStatus}: session expired or invalid.");
            }
```

  Подводные камни:
  - `Timeout waiting for Load Server` (следующий `throw` в том же блоке) остаётся `\RuntimeException` — это транспортный сбой, не протухшая сессия (INV-4).
  - Проверку `str_contains($body, 'ERROR')` делать **регистрозависимо**, как в эталоне.
  - Ничего не логировать с телом ответа, содержащим токен: в существующем `Log::info` уже логируется только ответ сервера — новых логов с `DSOAUTHTOKEN` не добавлять.

- [ ] **Ф4.3.** Файл `app/Services/TsoAmfService.php`, метод `sendServerCall()`. Якорь:

```php
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();

            if (str_contains($errorMsg, 'Load Server') || str_contains($errorMsg, '301') || str_contains($errorMsg, 'HTTP 500') || str_contains($errorMsg, 'HTTP 401') || str_contains($errorMsg, 'HTTP 403')) {
                try {
                    $this->authService->login($account);
                    $account->refresh();
                    $this->invalidateSession($account->id);

                    $call = $this->buildServerCall($account, $commandType, $actionData, $targetZoneId);

                    return $this->client->sendCommand($account, $call, $destination, $operation, $source, $targetZoneId);
                } catch (Exception $retryException) {
                    throw new \RuntimeException($e->getMessage().' (Auto-relogin also failed: '.$retryException->getMessage().')');
                }
            }

            throw $e;
        }
```

  Заменить на:

```php
        } catch (SessionExpiredException $e) {
            Log::info("[TsoAmf] Session rejected for account #{$account->id}; performing a single forced re-login and retry");

            try {
                $this->authService->resetSession($account);
                $this->authService->login($account);
                $account->refresh();
                $this->invalidateSession($account->id);

                $call = $this->buildServerCall($account, $commandType, $actionData, $targetZoneId);

                return $this->client->sendCommand($account, $call, $destination, $operation, $source, $targetZoneId);
            } catch (Exception $retryException) {
                throw new \RuntimeException($e->getMessage().' (Auto-relogin also failed: '.$retryException->getMessage().')');
            }
        }
```

  И добавить импорт в этот файл:

```php
use App\Exceptions\SessionExpiredException;
```

  Подводные камни:
  - `resetSession()` перед `login()` обязателен (INV-3), иначе дедупликация из Ф3.5 посчитает сессию свежей и пропустит нужный логин.
  - Ретрай остаётся **однократным**. Второй `SessionExpiredException` внутри `catch` не перехватывается повторно — он поднимется как `\RuntimeException` с текстом про неудавшийся релогин.
  - Прежний блок `catch (Exception $e)` с проверкой подстрок **удаляется полностью**: `HTTP 500` больше не приводит к релогину (INV-4), а `Load Server`/`301`/`401`/`403` теперь приходят типизированно.
  - Импорт `Exception` в файле уже есть (используется в `catch (Exception $retryException)`), удалять его нельзя.

- [ ] **Ф4.4.** Гейты [../quickstart.md](../quickstart.md) зелёные. Коммит: `fix(amf): detect rejected sessions on load server authenticate`.

---

