<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Models\Account;
use App\Services\TsoAuthService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\AuthTransportLoopbackServer;
use Tests\TestCase;

final class TsoAuthTransportUnknownTest extends TestCase
{
    private AuthTransportLoopbackServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'game.http_timeout' => 1]);
        Cache::flush();
        $this->server = new AuthTransportLoopbackServer;
    }

    protected function tearDown(): void
    {
        $this->server->stop();
        unset($this->server);
        parent::tearDown();
    }

    /** @return Account&MockInterface */
    private function account(string $path): Account
    {
        $account = Mockery::mock(Account::class)->makePartial();
        $account->forceFill([
            'id' => 984201,
            'dso_auth_user' => 'synthetic-user',
            'dso_auth_token' => 'synthetic-token',
            'bb_url' => $this->server->url.$path,
        ]);

        return $account;
    }

    /** @return TsoAuthService&MockInterface */
    private function service(): TsoAuthService
    {
        $service = Mockery::mock(TsoAuthService::class)->makePartial();
        $service->shouldReceive('getCookieFile')->andReturn($this->server->directory.'/cookies.txt');

        return $service;
    }

    /** @return array<string, array{string, bool}> */
    public static function unknownResponses(): array
    {
        return [
            'HTTP 500 despite ERROR body' => ['/status/500', false],
            'HTTP 502 despite ERROR body' => ['/status/502', false],
            'HTTP 503' => ['/status/503', false],
            'HTTP 429' => ['/status/429', false],
            'redirect' => ['/redirect/302', false],
            'timeout' => ['/timeout', false],
            'connection refused' => ['/ok', true],
        ];
    }

    #[DataProvider('unknownResponses')]
    public function test_unknown_probe_aborts_without_login_or_token_change(string $path, bool $stopServer): void
    {
        $account = $this->account($path);
        $account->shouldReceive('refresh')->never();
        $service = $this->service();
        $service->shouldReceive('login')->never();
        if ($stopServer) {
            $this->server->stop();
        }
        try {
            $service->ensureAuthenticated($account);
            $this->fail('An unknown probe must abort authentication.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('authentication deferred', $e->getMessage());
        }
        $this->assertSame('synthetic-token', $account->dso_auth_token);
        $this->assertSame('synthetic-user', $account->dso_auth_user);
        $this->assertFalse($account->exists);
        $this->assertNull(Cache::get('tso:session_ok:984201'));
        $this->assertNull(Cache::get('tso:session_invalidated:984201'));
        $this->assertCount($stopServer ? 0 : 1, $this->server->requests());
    }

    /** @return list<array{string}> */
    public static function rejectedResponses(): array
    {
        return [['/rejected'], ['/status/401'], ['/status/403']];
    }

    #[DataProvider('rejectedResponses')]
    public function test_confirmed_rejection_retains_login_path(string $path): void
    {
        $account = $this->account($path);
        $account->shouldReceive('refresh')->once()->andReturnSelf();
        $service = $this->service();
        $service->shouldReceive('login')->once()->with($account)->andReturn([]);
        $service->ensureAuthenticated($account);
        $this->assertCount(1, $this->server->requests());
    }

    public function test_success_is_cached_without_login(): void
    {
        $account = $this->account('/ok');
        $account->shouldReceive('refresh')->never();
        $service = $this->service();
        $service->shouldReceive('login')->never();
        $service->ensureAuthenticated($account);
        $service->ensureAuthenticated($account);
        $this->assertTrue(Cache::get('tso:session_ok:984201'));
        $this->assertCount(1, $this->server->requests());
    }

    public function test_explicit_force_still_uses_login_without_probe(): void
    {
        $account = $this->account('/ok');
        $account->shouldReceive('refresh')->once()->andReturnSelf();
        $service = $this->service();
        $service->shouldReceive('login')->once()->with($account)->andReturn([]);
        $service->ensureAuthenticated($account, force: true);
        $this->assertSame([], $this->server->requests());
    }

    public function test_missing_token_retains_login_without_probe(): void
    {
        $account = $this->account('/ok');
        $account->dso_auth_token = null;
        $account->shouldReceive('refresh')->once()->andReturnSelf();
        $service = $this->service();
        $service->shouldReceive('login')->once()->with($account)->andReturn([]);
        $service->ensureAuthenticated($account);
        $this->assertSame([], $this->server->requests());
    }

    public function test_explicit_invalidation_retains_login_without_probe(): void
    {
        $account = $this->account('/ok');
        $account->shouldReceive('refresh')->once()->andReturnSelf();
        Cache::put('tso:session_invalidated:984201', true, 300);
        $service = $this->service();
        $service->shouldReceive('login')->once()->with($account)->andReturn([]);
        $service->ensureAuthenticated($account);
        $this->assertSame([], $this->server->requests());
    }
}
