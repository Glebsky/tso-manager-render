# Фаза Ф5. Скользящий TTL игровой сессии

> Часть спецификации **SPEC-001-session-reuse**. Навигация: [README](../README.md) · [spec](../spec.md) · [plan](../plan.md) · [tasks](../tasks.md) · [constitution](../constitution.md)


Файлы: `app/Services/Amf/Transport/HttpTsoClient.php`, `config/game.php`, `.env.example`.

> Сейчас `resolved_at` обновляется только если сервер прислал новый `DSId`. Значит активно используемая сессия жёстко умирает через `session_ttl_seconds` (300 c), после чего выполняется новый `/authenticate` + `Z…` — а это **создание новой игровой сессии** и прямой путь к `1012`. Эталонный клиент держит одну сессию весь сеанс.

- [ ] **Ф5.1.** В методе `dispatchCommand()` заменить финальный блок. Якорь (дословно, включая `return $response;`):

```php
        $serverDsId = $this->extractDsIdFromResponse($response);

        if ($serverDsId !== null && $serverDsId !== $client['ds_id']) {
            Log::info("[TsoAmf] Game server assigned DSId {$serverDsId} for account #{$accountId} (was {$clientDsId})");

            $session['ds_id'] = $serverDsId;
            $session['resolved_at'] = microtime(true);
            $this->clients[$clientKey] = $session;
            $this->dsIds[$accountId] = $serverDsId;
            $this->writeSharedSession($accountId, $zoneId, $session);
        }

        return $response;
```

  Заменить на:

```php
        $serverDsId = $this->extractDsIdFromResponse($response);

        if ($serverDsId !== null && $serverDsId !== $client['ds_id']) {
            Log::info("[TsoAmf] Game server assigned DSId {$serverDsId} for account #{$accountId} (was {$clientDsId})");

            $session['ds_id'] = $serverDsId;
            $this->dsIds[$accountId] = $serverDsId;
        }

        // Sliding TTL: a session that keeps being used must not expire, because
        // re-resolving it creates a brand-new game session (error 1012). This
        // runs inside the per-account lock taken by sendCommand().
        $session['resolved_at'] = microtime(true);
        $this->clients[$clientKey] = $session;
        $this->writeSharedSession($accountId, $zoneId, $session);

        return $response;
```

  Подводные камни:
  - Обновление выполняется только на **успешном** пути (HTTP 200 уже проверен выше). Ни один `throw` выше не должен продлевать сессию.
  - Запись идёт под блокировкой `tso:amf_lock:{account}`, взятой в `sendCommand()` — гонка исключена.
  - Не менять формат записи сессии: ключи `url`, `cookie_file`, `ds_id`, `auth_token`, `resolved_at` проверяются в `readSharedSession()`.

- [ ] **Ф5.2.** `config/game.php` — поднять значение по умолчанию. Якорь:

```php
    'session_ttl_seconds' => (int) env('TSO_SESSION_TTL', 300),
```

  Заменить на:

```php
    'session_ttl_seconds' => (int) env('TSO_SESSION_TTL', 1800),
```

  В комментарии выше этой опции добавить последним абзацем:

```
    | The TTL is sliding: every successful AMF command refreshes it, so an
    | actively used session never expires. The value only limits how long an
    | idle session is kept before a fresh one is resolved.
```

- [ ] **Ф5.3.** `.env.example` — добавить строку в блок `# TSO Game Settings`, сразу после `TSO_HTTP_TIMEOUT=30`:

```
TSO_SESSION_TTL=1800
```

- [ ] **Ф5.4.** Гейты [../quickstart.md](../quickstart.md) зелёные. Коммит: `fix(amf): keep active game sessions alive with a sliding ttl`.

---

