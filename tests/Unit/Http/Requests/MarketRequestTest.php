<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Market\MarketSyncLogRequest;
use App\Http\Requests\Market\MarketVersionRequest;
use Tests\TestCase;

final class MarketRequestTest extends TestCase
{
    public function test_sync_log_request_authorizes_and_extracts_params(): void
    {
        $request = new MarketSyncLogRequest(['server_id' => 'ru_1', 'limit' => 25]);

        $this->assertTrue($request->authorize());
        $this->assertSame('ru_1', $request->serverId());
        $this->assertSame(25, $request->limit());

        $requestClamped = new MarketSyncLogRequest(['limit' => 500]);
        $this->assertSame(100, $requestClamped->limit());
    }

    public function test_version_request_authorizes_and_extracts_params(): void
    {
        $request = new MarketVersionRequest(['server_id' => 'de_2']);

        $this->assertTrue($request->authorize());
        $this->assertSame('de_2', $request->serverId());
    }

    public function test_login_request_helpers(): void
    {
        $request = new LoginRequest([
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'remember' => true,
        ]);

        $this->assertTrue($request->authorize());
        $this->assertSame('admin@example.com', $request->credentials()['email']);
        $this->assertSame('secret123', $request->credentials()['password']);
        $this->assertTrue($request->remember());
    }

    public function test_register_and_logout_requests_authorize(): void
    {
        $register = new RegisterRequest;
        $this->assertTrue($register->authorize());
        $this->assertArrayHasKey('name', $register->rules());
        $this->assertArrayHasKey('email', $register->rules());
        $this->assertArrayHasKey('password', $register->rules());

        $logout = new LogoutRequest;
        $this->assertTrue($logout->authorize());
        $this->assertSame([], $logout->rules());
    }
}
