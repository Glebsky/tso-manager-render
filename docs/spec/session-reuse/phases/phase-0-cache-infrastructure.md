# Фаза Ф0. Инфраструктура кэша — общая сессия между процессами

> Часть спецификации **SPEC-001-session-reuse**. Навигация: [README](../README.md) · [spec](../spec.md) · [plan](../plan.md) · [tasks](../tasks.md) · [constitution](../constitution.md)

### Шаги

- [ ] **Ф0.1.** Проверить фактические драйверы на всех процессах:

```bash
docker compose exec app php artisan tinker --execute="echo config('cache.default');"
docker compose exec worker php artisan tinker --execute="echo config('cache.default');"
docker compose exec scheduler php artisan tinker --execute="echo config('cache.default');"
grep -nE '^(CACHE_DRIVER|QUEUE_CONNECTION|SESSION_DRIVER)=' .env
```

  Ожидаемо: везде `redis`. Любое `file`/`array`/`database` — дефект инфраструктуры; зафиксировать в [../research.md](../research.md) и сообщить владельцу (правку `.env` делает человек, агент `.env` не трогает).

- [ ] **Ф0.2.** В `.env.example` заменить три строки (якоря дословные):

```diff
-CACHE_DRIVER=file
+CACHE_DRIVER=redis
-QUEUE_CONNECTION=sync
+QUEUE_CONNECTION=redis
-SESSION_DRIVER=file
+SESSION_DRIVER=redis
```

  Подводный камень: в файле есть также `BROADCAST_DRIVER=log` и `FILESYSTEM_DISK=local` — их **не менять**.

- [ ] **Ф0.3.** Проверить `docker-compose.yml`: у сервисов `app`, `worker`, `scheduler` в `environment` должны присутствовать `CACHE_DRIVER=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`, `REDIS_HOST=redis`.

```bash
grep -nE 'CACHE_DRIVER|QUEUE_CONNECTION|SESSION_DRIVER|REDIS_HOST' docker-compose.yml
```

  Если всё на месте — правок нет, шаг отмечается как выполненный.

- [ ] **Ф0.4.** Добавить защитное предупреждение при загрузке приложения. Файл `app/Providers/TsoServiceProvider.php`.

  Якорь (дословно):

```php
    public function boot(): void {}
```

  Заменить на:

```php
    public function boot(): void
    {
        $this->warnAboutNonSharedCache();
    }

    /**
     * The game session and its lock are shared through the cache. A per-process
     * cache store (file/array) means every process creates its own game session,
     * which the game server reports as error 1012 to whoever is not the newest
     * owner. Warn loudly instead of failing silently.
     */
    private function warnAboutNonSharedCache(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        $store = (string) config('cache.default');

        if (in_array($store, ['array', 'file'], true) && config('game.scheduler_mode') !== 'sync') {
            Log::warning(
                "[TsoConfig] Cache store '{$store}' is not shared between processes; "
                .'game sessions cannot be reused across web/worker/scheduler. Use redis.'
            );
        }
    }
```

  И добавить импорт в блок `use` этого файла (после существующих, порядок по алфавиту — Pint не сортирует импорты автоматически, но проект держит их отсортированными):

```php
use Illuminate\Support\Facades\Log;
```

  Подводные камни:
  - `runningUnitTests()` обязателен: в `phpunit.xml` задан `CACHE_DRIVER=array`, без гварда каждый тест засорит лог.
  - Это **только предупреждение**. Исключение бросать нельзя — иначе локальная разработка на file-кэше перестанет запускаться.

- [ ] **Ф0.5.** Гейты [../quickstart.md](../quickstart.md) зелёные. Коммит: `chore(config): default to shared redis cache and warn on per-process stores`.

---

