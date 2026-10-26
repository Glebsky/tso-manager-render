<?php

declare(strict_types=1);

namespace Tests\Unit\Amf;

use App\Models\Account;
use App\Services\Amf\Transport\HttpTsoClient;
use App\Services\TsoAuthService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use RuntimeException;
use Tests\Support\AuthTransportLoopbackServer;
use Tests\TestCase;

final class HttpTsoClientRedirectSafetyTest extends TestCase
{
    #[DataProvider('redirectStatuses')]
    public function test_dispatch_never_follows_redirect_or_repeats_post(int $status): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();
        $server = new AuthTransportLoopbackServer;
        try {
            $account = new Account;
            $account->forceFill(['id' => 984202, 'dso_auth_token' => 'synthetic-token']);
            $auth = Mockery::mock(TsoAuthService::class);
            $auth->shouldReceive('login')->never();
            $auth->shouldReceive('ensureAuthenticated')->never();
            $client = new HttpTsoClient($auth);
            $session = [
                'url' => $server->url.'/redirect/'.$status.'/amf',
                'cookie_file' => $server->directory.'/cookies.txt',
                'ds_id' => 'synthetic-dsid',
                'auth_token' => 'synthetic-token',
                'resolved_at' => microtime(true),
            ];
            // Bypass authentication/LS discovery: only exercise actual AMF dispatch.
            $clients = new ReflectionProperty($client, 'clients');
            $clients->setValue($client, ['984202:0' => $session]);
            try {
                $client->sendCommand($account, (object) ['synthetic' => true]);
                $this->fail('Redirect must leave command outcome unconfirmed.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('outcome unconfirmed', $e->getMessage());
                $this->assertStringContainsString('HTTP '.$status, $e->getMessage());
                $this->assertStringNotContainsString('synthetic redirect', $e->getMessage());
            }
            $this->assertSame([
                ['method' => 'POST', 'path' => '/redirect/'.$status.'/amf'],
            ], $server->requests());
            $this->assertSame(['984202:0' => $session], $clients->getValue($client));
            $lock = Cache::lock('tso:amf_lock:984202', 5);
            $this->assertTrue($lock->get());
            $lock->release();
        } finally {
            $server->stop();
        }
    }

    public function test_successful_dispatch_returns_response_with_one_post(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();
        $server = new AuthTransportLoopbackServer;
        try {
            $account = new Account;
            $account->forceFill(['id' => 984202, 'dso_auth_token' => 'synthetic-token']);
            $auth = Mockery::mock(TsoAuthService::class);
            $auth->shouldReceive('login')->never();
            $auth->shouldReceive('ensureAuthenticated')->never();
            $client = new HttpTsoClient($auth);
            $clients = new ReflectionProperty($client, 'clients');
            $clients->setValue($client, ['984202:0' => [
                'url' => $server->url.'/ok/amf',
                'cookie_file' => $server->directory.'/cookies.txt',
                'ds_id' => 'synthetic-dsid',
                'auth_token' => 'synthetic-token',
                'resolved_at' => microtime(true),
            ]]);
            $this->assertSame('synthetic success', $client->sendCommand($account, (object) ['synthetic' => true]));
            $this->assertSame([['method' => 'POST', 'path' => '/ok/amf']], $server->requests());
        } finally {
            $server->stop();
        }
    }

    /** @return list<array{int}> */
    public static function redirectStatuses(): array
    {
        return array_map(fn (int $status): array => [$status], [300, 301, 302, 303, 304, 307, 308, 399]);
    }
}
