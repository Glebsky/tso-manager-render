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
