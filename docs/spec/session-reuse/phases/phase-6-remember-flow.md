# Фаза Ф6. Память о рабочем флоу логина

> Часть спецификации **SPEC-001-session-reuse**. Навигация: [README](../README.md) · [spec](../spec.md) · [plan](../plan.md) · [tasks](../tasks.md) · [constitution](../constitution.md)


Файл: `app/Services/TsoAuthService.php`, метод `performLogin()`.

> Сейчас всегда сначала пробуется `loginLegacy()`, и только при исключении — `loginOAuth()`. Для аккаунта, миграированного в Ubisoft Connect, это гарантированно один провальный вход при каждом логине: лишняя задержка и накрутка неудачных попыток, за которой следует капча (а капча ставит аккаунт в 15-минутный кулдаун со статусом `session_expired`). Эталон выбирает ветку по настройке `cipMigrated`. Мы запоминаем сработавшую ветку в кэше — как уже сделано для `tso:client_id`, без миграций.

- [ ] **Ф6.1.** Добавить константу к остальным (рядом с `SESSION_OK_KEY`):

```php
    /** Cache key prefix remembering which login flow last succeeded. */
    private const string AUTH_FLOW_KEY = 'tso:auth_flow:';
```

- [ ] **Ф6.2.** Добавить два приватных хелпера (рядом с `sessionParams()`):

```php
    /**
     * Which login flow to try first. Mirrors the cipMigrated switch of the
     * reference client, but learned from the last successful login instead of
     * being configured by hand.
     *
     * @return 'legacy'|'oauth'
     */
    private function preferredFlow(Account $account): string
    {
        return Cache::get(self::AUTH_FLOW_KEY.$account->id) === 'oauth' ? 'oauth' : 'legacy';
    }

    private function rememberFlow(Account $account, string $flow): void
    {
        Cache::put(self::AUTH_FLOW_KEY.$account->id, $flow, now()->addDays(30));
    }
```

- [ ] **Ф6.3.** Заменить в `performLogin()` весь блок попыток логина. Якорь — от `try {` с вызовом `loginLegacy` до `Cache::forget($cooldownKey);` включительно:

```php
        try {
            $params = $this->loginLegacy($account, $cookieFile, $server);
        } catch (Exception $e) {
```

  … (весь существующий вложенный `try/catch` с `loginOAuth` и двумя проверками `isCaptchaOr2faError`) …

```php
        Cache::forget($cooldownKey);
```

  Заменить целиком на:

```php
        $order = $this->preferredFlow($account) === 'oauth'
            ? ['oauth', 'legacy']
            : ['legacy', 'oauth'];

        $params = null;
        $firstException = null;

        foreach ($order as $flow) {
            try {
                $params = $flow === 'oauth'
                    ? $this->loginOAuth($account, $cookieFile, $server)
                    : $this->loginLegacy($account, $cookieFile, $server);

                $this->rememberFlow($account, $flow);

                break;
            } catch (Exception $e) {
                $errMsg = $e->getMessage();
                Log::warning("[TsoAuth] {$flow} login failed for account #{$account->id}: ".CredentialRedactor::redact($errMsg, $account));

                if ($this->isCaptchaOr2faError($errMsg)) {
                    Cache::put($cooldownKey, $errMsg, 900);
                    $account->update(['status' => 'session_expired']);

                    throw $e;
                }

                $firstException ??= $e;
            }
        }

        if ($params === null) {
            throw $firstException ?? new RuntimeException("Login failed for account #{$account->id}");
        }

        Cache::forget($cooldownKey);
```

  Подводные камни:
  - Поведение при капче/2FA **не меняется**: кулдаун 900 c, статус `session_expired`, исключение наружу немедленно, вторая ветка не пробуется.
  - При провале обеих ветвей наружу отдаётся исключение **первой попробованной** ветки — так сохраняется текущее поведение (при `legacy`-первом это ровно прежний `throw $e`).
  - `resetSession($account)` перед циклом **остаётся на месте** (см. [../quickstart.md](../quickstart.md), п. 3). Полный логин обязан начинаться с чистой cookie-банки, как `new CookieCollection()` в эталоне.
  - Тексты логов остаются с префиксом `[TsoAuth]` и обязательно проходят через `CredentialRedactor::redact()`.
  - Ничего не логировать без redact: сообщения ошибок могут содержать логин/пароль.

- [ ] **Ф6.4.** Гейты [../quickstart.md](../quickstart.md) зелёные. Коммит: `perf(auth): remember the login flow that worked per account`.

---

