<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Services\Auth\TsoPlayPageParser;
use App\Services\Auth\UbisoftConnectAuthClient;
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

        $this->assertTrue((bool) Cache::get("tso:session_ok:{$account->id}"));
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
        Cache::put("account_login_cooldown:{$account->id}", 'cooldown', 300);
        Cache::put("tso:login_lock:{$account->id}", 'lock', 300);
        Cache::put("tso:auth_flow:{$account->id}", 'oauth', 300);

        $authService = $this->app->make(TsoAuthService::class);
        $cookieFile = $authService->getCookieFile($account);
        file_put_contents($cookieFile, 'dummy_cookies');
        $this->assertFileExists($cookieFile);

        $authService->resetSession($account);

        $this->assertFileDoesNotExist($cookieFile);
        $this->assertNull(Cache::get("tso:session_ok:{$account->id}"));
        $this->assertNull(Cache::get("account_login_cooldown:{$account->id}"));
        $this->assertNull(Cache::get("tso:login_lock:{$account->id}"));
        $this->assertNull(Cache::get("tso:auth_flow:{$account->id}"));
    }

    public function test_login_legacy_does_not_switch_flow_to_oauth_on_error(): void
    {
        $account = $this->makeAccount();
        $server = TsoAuthService::getServerConfig($account->region);
        $this->assertNotNull($server);

        $authService = new class($this->app->make(TsoPlayPageParser::class), $this->app->make(UbisoftConnectAuthClient::class)) extends TsoAuthService
        {
            protected function cipMigratedRequest(string $url, ?array $postData, string $cookieFile): string
            {
                if ($postData !== null) {
                    return 'Не удалось войти: Попытка входа не удалась. Если вы хотите войти в систему через Ubisoft Connect, нажмите на черную кнопку Ubisoft выше.';
                }

                return '<html>Homepage</html>';
            }
        };

        $cookieFile = $authService->getCookieFile($account);

        try {
            $authService->loginLegacy($account, $cookieFile, $server);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Login failed', $e->getMessage());
        }

        $this->assertNull(Cache::get("tso:auth_flow:{$account->id}"));
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
