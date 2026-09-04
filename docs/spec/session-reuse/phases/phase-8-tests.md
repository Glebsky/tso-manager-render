# Фаза Ф8. Тесты

> Часть спецификации **SPEC-001-session-reuse**. Навигация: [README](../README.md) · [spec](../spec.md) · [plan](../plan.md) · [tasks](../tasks.md) · [constitution](../constitution.md)


Проект использует **PHPUnit** (не Pest), `Tests\TestCase`, Mockery, `RefreshDatabase`. В `phpunit.xml` задано `CACHE_DRIVER=array`, `DB_CONNECTION=sqlite` (`:memory:`).

### 9.1. Починить существующие тесты (сделать ПЕРВЫМ)

- [ ] **Ф8.1.** Найти все моки старого метода:

```bash
grep -rn 'isAuthenticated' tests/
```

  В каждом найденном месте (как минимум `tests/Feature/TsoSessionIsolationTest.php`) заменить

```php
        $this->authMock->shouldReceive('isAuthenticated')->andReturn(true);
```

  на

```php
        $this->authMock->shouldReceive('ensureAuthenticated')->andReturnNull();
```

  Подводный камень: если в тесте дополнительно мокается `login`, ожидание `->never()`/`->once()` нужно пересмотреть — при `ensureAuthenticated`, замоканном на мок-объекте, реальный `login` не вызывается вообще.

### 9.2. Новые тесты

- [ ] **Ф8.2.** `tests/Feature/SessionReuseTest.php` — покрыть ровно эти сценарии (имена тестов сохранить):

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Services\TsoAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

final class SessionReuseTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(): Account
    {
        return Account::create([
            'username' => 'session_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'SessionUser',
            'dso_auth_user' => '3001',
            'dso_auth_token' => 'token_session',
            'bb_url' => 'https://r02-ls.thesettlersonline.ru/',
        ]);
    }

    public function test_ensure_authenticated_skips_probe_when_flag_is_cached(): void
    {
        $account = $this->makeAccount();
        Cache::put("tso:session_ok:{$account->id}", true, 300);

        $service = Mockery::mock(TsoAuthService::class)->makePartial();
        $service->shouldReceive('verifySession')->never();
        $service->shouldReceive('login')->never();

        $service->ensureAuthenticated($account);

        $this->assertTrue(true);
    }

    public function test_ensure_authenticated_reuses_live_session_without_login(): void
    {
        $account = $this->makeAccount();

        $service = Mockery::mock(TsoAuthService::class)->makePartial();
        $service->shouldReceive('verifySession')->once()->with($account)->andReturnTrue();
        $service->shouldReceive('login')->never();

        $service->ensureAuthenticated($account);

        $this->assertTrue(Cache::get("tso:session_ok:{$account->id}"));
    }

    public function test_ensure_authenticated_logs_in_when_session_is_dead(): void
    {
        $account = $this->makeAccount();

        $service = Mockery::mock(TsoAuthService::class)->makePartial();
        $service->shouldReceive('verifySession')->once()->andReturnFalse();
        $service->shouldReceive('login')->once()->with($account)->andReturn([]);

        $service->ensureAuthenticated($account);
    }

    public function test_reset_session_clears_the_verified_flag(): void
    {
        $account = $this->makeAccount();
        Cache::put("tso:session_ok:{$account->id}", true, 300);

        $this->app->make(TsoAuthService::class)->resetSession($account);

        $this->assertNull(Cache::get("tso:session_ok:{$account->id}"));
    }

    public function test_login_is_deduplicated_when_another_process_just_logged_in(): void
    {
        $account = $this->makeAccount();
        Cache::put("tso:session_ok:{$account->id}", true, 300);

        $params = $this->app->make(TsoAuthService::class)->login($account);

        $this->assertSame('token_session', $params['dsoAuthToken']);
        $this->assertSame('3001', $params['dsoAuthUser']);
        $this->assertSame('https://r02-ls.thesettlersonline.ru/', $params['bburl']);
    }
}
```

  Подводные камни:
  - `test_login_is_deduplicated_...` не должен уходить в сеть. Он проверяет именно ветку из Ф3.5. Если тест «висит» или падает с cURL-ошибкой — дедупликация реализована неверно.
  - Ключи кэша в тестах прописаны литералами намеренно: это защита от случайного переименования (`tso:session_ok:`).
  - `Mockery::mock(...)->makePartial()` обязателен: тестируется реальный `ensureAuthenticated()` при замоканных зависимостях того же класса.

- [ ] **Ф8.3.** `tests/Feature/AuthDebugDumpRemovedTest.php` — регрессия на Ф1:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class AuthDebugDumpRemovedTest extends TestCase
{
    public function test_auth_service_never_dumps_play_page_or_flash_vars(): void
    {
        $source = (string) file_get_contents(app_path('Services/TsoAuthService.php'));

        $this->assertStringNotContainsString('debug/play_page.html', $source);
        $this->assertStringNotContainsString('debug/flash_vars.json', $source);
        $this->assertStringNotContainsString("Storage::disk('local')", $source);
    }
}
```

- [ ] **Ф8.4.** Прогнать полный набор и убедиться, что **ни один** ранее зелёный тест не покраснел:

```bash
php artisan test
```

  Особое внимание: `tests/Feature/TsoSessionIsolationTest.php`, `tests/Feature/TaskExecutionCharacterizationTest.php`, `tests/Feature/AccountSyncTest.php`, `tests/Feature/SchedulerArchitectureTest.php`, `tests/Feature/SingleOperatorInvariantTest.php`, `tests/Feature/SecurityHardeningTest.php`.

- [ ] **Ф8.5.** Коммит: `test(session): cover session reuse and debug dump removal`.

---

