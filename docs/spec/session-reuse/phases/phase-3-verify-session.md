# Фаза Ф3. verifySession() + ensureAuthenticated() — аналог FastAuth()

> Часть спецификации **SPEC-001-session-reuse**. Навигация: [README](../README.md) · [spec](../spec.md) · [plan](../plan.md) · [tasks](../tasks.md) · [constitution](../constitution.md)


Файл: `app/Services/TsoAuthService.php`.

- [ ] **Ф3.1.** Добавить константы сразу **после** закрывающей скобки константы `SERVERS` (якорь — строка `];` и следующая за ней пустая строка перед docblock метода `getCookieFile`):

```php
    /**
     * Browser fingerprint expected by the game backend. Must stay byte-identical
     * to what the reference Flash client sends.
     */
    private const array BROWSER_HEADERS = [
        'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.570.0 Safari/534.12',
        'Referer: http://game-cdn.thesettlersonline.net/prestaging/PS5724/SWMMO/debug/SWMMO.swf',
    ];

    /** Cache key prefix for the "session was verified recently" flag. */
    private const string SESSION_OK_KEY = 'tso:session_ok:';

    /** How long a positive verifySession() result is trusted, in seconds. */
    private const int SESSION_OK_TTL = 300;
```

- [ ] **Ф3.2.** DRY: в приватном методе `curlRequest()` заменить локальный массив заголовков на константу. Якорь:

```php
        $defaultHeaders = [
            'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.12 (KHTML, like Gecko) Chrome/9.0.570.0 Safari/534.12',
            'Referer: http://game-cdn.thesettlersonline.net/prestaging/PS5724/SWMMO/debug/SWMMO.swf',
        ];
```

  Заменить на:

```php
        $defaultHeaders = self::BROWSER_HEADERS;
```

  Подводный камень: порядок и содержимое заголовков остаются идентичными — это чистый рефакторинг без изменения поведения.

- [ ] **Ф3.3.** Добавить методы `verifySession()` и `ensureAuthenticated()` **непосредственно перед** существующим методом `isAuthenticated()` (якорь — его docblock `/**\n     * Check if the account already has valid tokens.\n     */`):

```php
    /**
     * Cheap liveness probe for the stored session, mirroring FastAuth() in the
     * reference client (client/login.xaml.cs): one POST to bb/authenticate that
     * both validates the tokens and refreshes the cookie jar.
     *
     * Returns false only when we can positively tell the session is unusable.
     */
    public function verifySession(Account $account): bool
    {
        if (! $this->isAuthenticated($account)) {
            return false;
        }

        $cookieFile = $this->getCookieFile($account);
        if (! is_file($cookieFile)) {
            // Tokens without a cookie jar cannot authenticate against the game.
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, rtrim((string) $account->bb_url, '/').'/authenticate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'DSOAUTHUSER' => (string) $account->dso_auth_user,
            'DSOAUTHTOKEN' => (string) $account->dso_auth_token,
        ]));
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, (bool) config('game.ssl_verify', true));
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) config('game.http_timeout', 30));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(
            ['Content-Type: application/x-www-form-urlencoded'],
            self::BROWSER_HEADERS,
        ));

        $response = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '') {
            Log::warning("[TsoAuth] Session probe for account #{$account->id} failed on transport level: {$error}");

            return false;
        }

        $alive = $status === 200 && ! str_contains($response, 'ERROR');

        Log::info("[TsoAuth] Session probe for account #{$account->id}: HTTP {$status}, alive=".($alive ? 'yes' : 'no'));

        return $alive;
    }

    /**
     * The only entry point application services should use: make sure the
     * account can talk to the game server, reusing the existing session
     * whenever possible and logging in only when it is actually dead.
     *
     * @throws Exception
     */
    public function ensureAuthenticated(Account $account): void
    {
        if (Cache::get(self::SESSION_OK_KEY.$account->id) === true) {
            return;
        }

        if ($this->verifySession($account)) {
            $this->markSessionVerified($account);

            return;
        }

        $this->login($account);
        $account->refresh();
    }

    private function markSessionVerified(Account|int $account): void
    {
        $id = $account instanceof Account ? $account->id : $account;

        Cache::put(self::SESSION_OK_KEY.$id, true, self::SESSION_OK_TTL);
    }

    private function forgetSessionVerified(Account|int $account): void
    {
        $id = $account instanceof Account ? $account->id : $account;

        Cache::forget(self::SESSION_OK_KEY.$id);
    }
```

  Подводные камни:
  - `verifySession()` возвращает `false` и при сетевом сбое. Это осознанный компромисс: дальше будет попытка полного логина, которая при недоступной сети упадёт с внятной ошибкой. Логика «сеть недоступна → считаем сессию живой» усложняет код и маскирует проблему (KISS).
  - `verifySession()` **не должен** вызывать `resetSession()` — иначе проба сама уничтожит cookies, которые проверяет.
  - Никаких `Cache::lock` внутри `verifySession()`: проба идемпотентна и не создаёт игровую сессию.

- [ ] **Ф3.4.** Сбрасывать флаг при сбросе сессии. В методе `resetSession()` якорь:

```php
    public function resetSession(Account $account): void
    {
        $cookieFile = $this->getCookieFile($account);
        if (is_file($cookieFile)) {
            @unlink($cookieFile);
        }
    }
```

  Заменить на:

```php
    public function resetSession(Account $account): void
    {
        $cookieFile = $this->getCookieFile($account);
        if (is_file($cookieFile)) {
            @unlink($cookieFile);
        }

        $this->forgetSessionVerified($account);
    }
```

- [ ] **Ф3.5.** Дедупликация одновременных логинов. Метод `login()` — якорь:

```php
        try {
            return $this->performLogin($account);
        } finally {
            if ($locked) {
                $lock->release();
            }
        }
```

  Заменить на:

```php
        try {
            if ($locked) {
                // Another process may have completed a full login while we were
                // waiting for the lock. Logging in again would create a second
                // game session and invalidate the fresh one (error 1012).
                $account->refresh();

                if (Cache::get(self::SESSION_OK_KEY.$account->id) === true) {
                    Log::info("[TsoAuth] Reusing session established by another process for account #{$account->id}");

                    return $this->sessionParams($account);
                }
            }

            return $this->performLogin($account);
        } finally {
            if ($locked) {
                $lock->release();
            }
        }
```

  И добавить приватный хелпер (рядом с `markSessionVerified()`):

```php
    /**
     * Current session parameters in the same shape performLogin() returns.
     *
     * @return array<string, mixed>
     */
    private function sessionParams(Account $account): array
    {
        return [
            'dsoAuthToken' => (string) $account->dso_auth_token,
            'dsoAuthUser' => (string) $account->dso_auth_user,
            'bburl' => (string) $account->bb_url,
            'zoneId' => null,
            'nickName' => (string) $account->nickname,
        ];
    }
```

  Подводные камни:
  - Ключи возвращаемого массива обязаны совпадать с `performLogin()`: `dsoAuthToken`, `dsoAuthUser`, `bburl`, `zoneId`, `nickName`. Именно `bburl` в нижнем регистре без подчёркивания.
  - Дедупликация работает **только** вместе с INV-3: тот, кому нужен принудительный логин, обязан сначала вызвать `resetSession()` (он сбрасывает флаг). Все существующие обработчики `1005`/`1012` так и делают — кроме `TsoAmfService::sendServerCall()`, который исправляется в Ф4.3.
  - Если блокировку взять не удалось (`$locked === false`), проверка пропускается и выполняется обычный `performLogin()` — прежнее поведение сохранено.

- [ ] **Ф3.6.** В конце `performLogin()`, сразу **после** блока `$account->update([...])` (якорь — строка `'status' => 'online',` и закрывающая `]);`) и **до** `return $params;` добавить:

```php
        $this->markSessionVerified($account);
```

- [ ] **Ф3.7.** Гейты [../quickstart.md](../quickstart.md) зелёные. Коммит: `feat(auth): reuse live sessions via cheap authenticate probe`.

---

