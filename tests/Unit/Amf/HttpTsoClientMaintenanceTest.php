<?php

declare(strict_types=1);

namespace Tests\Unit\Amf;

use App\Exceptions\GameServerMaintenanceException;
use App\Exceptions\SessionExpiredException;
use App\Models\Account;
use App\Services\Amf\Transport\HttpTsoClient;
use App\Services\TsoAuthService;
use Mockery;
use ReflectionMethod;
use RuntimeException;
use Tests\Support\AuthTransportLoopbackServer;
use Tests\TestCase;

class HttpTsoClientMaintenanceTest extends TestCase
{
    public function test_is_maintenance_response_identifies_maintenance_status_and_keywords(): void
    {
        $client = app(HttpTsoClient::class);
        $method = new ReflectionMethod($client, 'isMaintenanceResponse');

        $this->assertTrue($method->invoke($client, 503, ''));
        $this->assertTrue($method->invoke($client, 200, 'Server maintenance in progress'));
        $this->assertTrue($method->invoke($client, 200, 'Wartungsarbeiten'));
        $this->assertTrue($method->invoke($client, 200, 'Техническое обслуживание'));

        $this->assertFalse($method->invoke($client, 200, 'http://game-01.thesettlersonline.com/amf'));
        $this->assertFalse($method->invoke($client, 202, 'queuePos=1&queueSize=2'));
    }

    public function test_from_response_formats_queue_pos_and_size(): void
    {
        $ex = GameServerMaintenanceException::fromResponse(202, 'queuePos=2&queueSize=2');
        $this->assertInstanceOf(GameServerMaintenanceException::class, $ex);
        $this->assertSame(503, $ex->httpStatus());
        $payload = $ex->toPayload();
        $this->assertSame('tasks.error.server_maintenance_queue', $payload['key']);
        $this->assertSame(2, $payload['params']['pos']);
        $this->assertSame(2, $payload['params']['size']);
    }

    public function test_resolve_server_url_fails_fast_on_auth_522_without_polling_z(): void
    {
        $server = new AuthTransportLoopbackServer;
        config(['game.load_server_retry_delay_seconds' => 0]);

        try {
            $authService = Mockery::mock(TsoAuthService::class)->makePartial();
            $authService->shouldReceive('getCookieFile')->andReturn($server->directory.'/cookies.txt');

            $account = Mockery::mock(Account::class)->makePartial();
            $account->forceFill([
                'id' => 991,
                'dso_auth_user' => 'u1',
                'dso_auth_token' => 't1',
                'bb_url' => $server->url.'/outage/522/',
            ]);

            $client = new HttpTsoClient($authService);

            try {
                $client->resolveServerUrl($account);
                $this->fail('Expected RuntimeException on HTTP 522 load server auth');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('522', $e->getMessage());
            }

            // Must stop right at /authenticate and never enter /Z retry loop
            $this->assertCount(1, $server->requests());
        } finally {
            $server->stop();
        }
    }

    public function test_resolve_server_url_fails_fast_after_consecutive_z_outage_errors(): void
    {
        $server = new AuthTransportLoopbackServer;
        config(['game.load_server_max_retries' => 10, 'game.load_server_retry_delay_seconds' => 0]);

        try {
            $authService = Mockery::mock(TsoAuthService::class)->makePartial();
            $authService->shouldReceive('getCookieFile')->andReturn($server->directory.'/cookies.txt');

            $account = Mockery::mock(Account::class)->makePartial();
            $account->forceFill([
                'id' => 992,
                'dso_auth_user' => 'u1',
                'dso_auth_token' => 't1',
                'bb_url' => $server->url.'/ls-outage/',
            ]);

            $client = new HttpTsoClient($authService);

            try {
                $client->resolveServerUrl($account);
                $this->fail('Expected RuntimeException after consecutive HTTP 522 responses');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('522', $e->getMessage());
            }

            // 1 authenticate + 2 /Z attempts = 3 requests total (instead of 11)
            $this->assertCount(3, $server->requests());
        } finally {
            $server->stop();
        }
    }

    public function test_resolve_server_url_throws_queue_busy_message_when_stuck_in_202_queue(): void
    {
        $server = new AuthTransportLoopbackServer;
        config(['game.load_server_max_retries' => 2, 'game.load_server_retry_delay_seconds' => 0]);

        try {
            $authService = Mockery::mock(TsoAuthService::class)->makePartial();
            $authService->shouldReceive('getCookieFile')->andReturn($server->directory.'/cookies.txt');

            $account = Mockery::mock(Account::class)->makePartial();
            $account->forceFill([
                'id' => 993,
                'dso_auth_user' => 'u1',
                'dso_auth_token' => 't1',
                'bb_url' => $server->url.'/ls-queue/',
            ]);

            $client = new HttpTsoClient($authService);

            $this->expectException(GameServerMaintenanceException::class);
            $client->resolveServerUrl($account);
        } finally {
            $server->stop();
        }
    }

    public function test_from_response_parses_uls_json_queue_and_maintenance(): void
    {
        $queued = GameServerMaintenanceException::fromResponse(
            200,
            '{"queuePosition":3,"queueLength":7,"retryAfter":5,"state":"queued"}'
        );
        $queuedPayload = $queued->toPayload();
        $this->assertSame('tasks.error.server_maintenance_queue', $queuedPayload['key']);
        $this->assertSame(3, $queuedPayload['params']['pos']);
        $this->assertSame(7, $queuedPayload['params']['size']);

        $maint = GameServerMaintenanceException::fromResponse(503, '{"code":"maintenance"}');
        $maintPayload = $maint->toPayload();
        $this->assertSame('tasks.error.server_maintenance_details', $maintPayload['key']);
        $this->assertSame('maintenance', $maintPayload['params']['details']);
    }

    public function test_resolve_server_url_supports_uls_assigned_url(): void
    {
        $server = new AuthTransportLoopbackServer;

        try {
            $authService = Mockery::mock(TsoAuthService::class)->makePartial();
            $authService->shouldReceive('getCookieFile')->andReturn($server->directory.'/cookies.txt');

            $account = Mockery::mock(Account::class)->makePartial();
            $account->forceFill([
                'id' => 994,
                'dso_auth_user' => '395894',
                'dso_auth_token' => 't1',
                'bb_url' => $server->url.'/uls/v1/territories/ru/realms/2',
            ]);

            $client = new HttpTsoClient($authService);
            $resolved = $client->resolveServerUrl($account, 395894);

            $this->assertSame('https://r02-gs003.thesettlersonline.ru/GameServer/amf', $resolved);
            $reqs = $server->requests();
            $this->assertCount(1, $reqs);
            $this->assertSame('/uls/v1/territories/ru/realms/2/zones/395894', $reqs[0]['path']);
        } finally {
            $server->stop();
        }
    }

    public function test_resolve_server_url_uls_queued_throws_queue_busy_exception(): void
    {
        $server = new AuthTransportLoopbackServer;
        config(['game.load_server_max_retries' => 2, 'game.load_server_retry_delay_seconds' => 0]);

        try {
            $authService = Mockery::mock(TsoAuthService::class)->makePartial();
            $authService->shouldReceive('getCookieFile')->andReturn($server->directory.'/cookies.txt');

            $account = Mockery::mock(Account::class)->makePartial();
            $account->forceFill([
                'id' => 995,
                'dso_auth_user' => '395894',
                'dso_auth_token' => 't1',
                'bb_url' => $server->url.'/uls-queue/v1/territories/de/realms/12',
            ]);

            $client = new HttpTsoClient($authService);

            try {
                $client->resolveServerUrl($account, 0);
                $this->fail('Expected GameServerMaintenanceException when stuck in ULS queue');
            } catch (GameServerMaintenanceException $e) {
                $payload = $e->toPayload();
                $this->assertSame('tasks.error.server_maintenance_queue', $payload['key']);
                $this->assertSame(2, $payload['params']['pos']);
                $this->assertSame(4, $payload['params']['size']);
            }
        } finally {
            $server->stop();
        }
    }

    public function test_resolve_server_url_uls_invalid_token_throws_session_expired(): void
    {
        $server = new AuthTransportLoopbackServer;
        config(['game.load_server_retry_delay_seconds' => 0]);

        try {
            $authService = Mockery::mock(TsoAuthService::class)->makePartial();
            $authService->shouldReceive('getCookieFile')->andReturn($server->directory.'/cookies.txt');

            $account = Mockery::mock(Account::class)->makePartial();
            $account->forceFill([
                'id' => 996,
                'dso_auth_user' => '395894',
                'dso_auth_token' => 't1',
                'bb_url' => $server->url.'/uls-expired/v1/territories/ru/realms/2',
            ]);

            $client = new HttpTsoClient($authService);

            $this->expectException(SessionExpiredException::class);
            $client->resolveServerUrl($account, 0);
        } finally {
            $server->stop();
        }
    }

    public function test_resolve_server_url_uls_fails_fast_on_unknown_realm_and_outage(): void
    {
        $server = new AuthTransportLoopbackServer;
        config(['game.load_server_max_retries' => 6, 'game.load_server_retry_delay_seconds' => 0]);

        try {
            $authService = Mockery::mock(TsoAuthService::class)->makePartial();
            $authService->shouldReceive('getCookieFile')->andReturn($server->directory.'/cookies.txt');

            $client = new HttpTsoClient($authService);

            $unknownAccount = Mockery::mock(Account::class)->makePartial();
            $unknownAccount->forceFill([
                'id' => 997,
                'dso_auth_user' => 'u1',
                'dso_auth_token' => 't1',
                'bb_url' => $server->url.'/uls-unknown-realm/v1/territories/en/realms/2',
            ]);

            try {
                $client->resolveServerUrl($unknownAccount, 0);
                $this->fail('Expected RuntimeException on HTTP 404 unknown_realm');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('404', $e->getMessage());
            }
            $this->assertCount(1, $server->requests());

            $outageAccount = Mockery::mock(Account::class)->makePartial();
            $outageAccount->forceFill([
                'id' => 998,
                'dso_auth_user' => 'u1',
                'dso_auth_token' => 't1',
                'bb_url' => $server->url.'/uls-outage/v1/territories/ru/realms/2',
            ]);

            try {
                $client->resolveServerUrl($outageAccount, 0);
                $this->fail('Expected RuntimeException on consecutive ULS HTTP 522 errors');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('522', $e->getMessage());
            }
            $this->assertCount(3, $server->requests());
        } finally {
            $server->stop();
        }
    }
}
