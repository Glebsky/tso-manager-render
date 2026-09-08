# Фаза Ф7. DRY — единая точка входа во всех вызывающих

> Часть спецификации **SPEC-001-session-reuse**. Навигация: [README](../README.md) · [spec](../spec.md) · [plan](../plan.md) · [tasks](../tasks.md) · [constitution](../constitution.md)


> Один и тот же трёхстрочный фрагмент скопирован в шести местах. Он заменяется на `ensureAuthenticated()`, который сам делает `refresh()`.

Шаблон замены. Якорь (встречается в каждом файле, отступ может отличаться):

```php
        if (! $this->authService->isAuthenticated($account)) {
            $this->authService->login($account);
            $account->refresh();
        }
```

Замена:

```php
        $this->authService->ensureAuthenticated($account);
```

Применить в каждом файле, сохраняя исходный отступ:

- [ ] **Ф7.1.** `app/Services/AccountService.php`, метод `executeAction()` (внутри `try`).
- [ ] **Ф7.2.** `app/Services/AccountService.php`, метод `getFriendZone()` (внутри `try`, отступ глубже — 16 пробелов).
- [ ] **Ф7.3.** `app/Services/TaskExecutionService.php`, метод `execute()`.
- [ ] **Ф7.4.** `app/Services/Tasks/Execution/SequenceStepExecutor.php`, метод `executeSingleStep()`.
- [ ] **Ф7.5.** `app/Services/Account/Sync/AccountSyncFetcher.php` — вызов **вне** блоков обработки `1005`/`1012` (тот, что предшествует циклу попыток). Вызовы `resetSession()` + `login()` внутри обработчиков ошибок **не трогать**.
- [ ] **Ф7.6.** `app/Services/Market/Sync/MarketOfferFetcher.php`, метод `fetch()` — первый вызов в начале метода. Обработчики `1005`/`1012` ниже **не трогать**.
- [ ] **Ф7.7.** Проверить, что не осталось прикладных вызовов старого шаблона:

```bash
grep -rn 'isAuthenticated' app/
```

  Допустимые совпадения: объявление метода в `TsoAuthService.php` и его вызов внутри `verifySession()`. Больше нигде.

- [ ] **Ф7.8.** Гейты [../quickstart.md](../quickstart.md) зелёные. Коммит: `refactor(auth): funnel all call sites through ensureAuthenticated`.

---

