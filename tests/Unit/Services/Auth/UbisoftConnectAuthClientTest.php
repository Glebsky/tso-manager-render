<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Models\Account;
use App\Services\Auth\TsoPlayPageParser;
use App\Services\Auth\UbisoftConnectAuthClient;
use App\Services\TsoAuthService;
use Mockery;
use Tests\TestCase;

final class UbisoftConnectAuthClientTest extends TestCase
{
    public function test_can_be_instantiated_directly_and_via_container(): void
    {
        $client = new UbisoftConnectAuthClient;
        $this->assertInstanceOf(UbisoftConnectAuthClient::class, $client);

        $resolved = $this->app->make(UbisoftConnectAuthClient::class);
        $this->assertInstanceOf(UbisoftConnectAuthClient::class, $resolved);
    }

    public function test_tso_auth_service_delegates_login_oauth_to_ubisoft_client(): void
    {
        $mockClient = Mockery::mock(UbisoftConnectAuthClient::class);
        $mockParser = new TsoPlayPageParser;
        $authService = new TsoAuthService($mockParser, $mockClient);

        $account = new Account;
        $account->id = 1;
        $cookieFile = 'test_cookie.txt';
        $server = ['domain' => 'https://example.com', 'uplay' => '/uplay', 'main' => '/main', 'play' => '/play'];
        $expectedResult = [
            'dsoAuthToken' => 'tok123',
            'dsoAuthUser' => 'user123',
            'bburl' => 'https://bb.example.com',
            'zoneId' => '1',
            'nickName' => 'Tester',
        ];

        $mockClient->shouldReceive('login')
            ->once()
            ->with($account, $cookieFile, $server)
            ->andReturn($expectedResult);

        $result = $authService->loginOAuth($account, $cookieFile, $server);

        $this->assertSame($expectedResult, $result);
    }
}
