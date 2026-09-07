<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\Amf\Transport\HttpTsoClient;
use App\Services\TsoAuthService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_present_on_web_and_api_responses(): void
    {
        $urls = [
            '/',
            '/healthz',
            '/api/public/market/version',
            '/admin/login',
        ];

        foreach ($urls as $url) {
            $response = $this->get($url);

            $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader('X-XSS-Protection', '1; mode=block');
            $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
            $response->assertHeader('Content-Security-Policy-Report-Only');
        }

        // Verify HSTS is emitted on HTTPS
        $secureResponse = $this->get('https://localhost/');
        $secureResponse->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_public_market_endpoints_are_rate_limited(): void
    {
        RateLimiter::clear('public-market');

        for ($i = 0; $i < 120; $i++) {
            $response = $this->getJson('/api/public/market/version');
            $this->assertNotEquals(429, $response->getStatusCode(), "Request #{$i} was unexpectedly rate limited.");
        }

        // 121st request should be throttled
        $responseThrottled = $this->getJson('/api/public/market/version');
        $this->assertEquals(429, $responseThrottled->getStatusCode(), 'Request exceeding 120/min was not throttled.');
    }

    public function test_sensitive_account_action_endpoints_are_rate_limited(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'username' => 'rate_test_user',
            'password' => 'secret123',
            'region' => 'en',
            'bb_url' => 'https://example.com/play',
        ]);

        RateLimiter::clear('api-actions');

        $this->actingAs($user);

        for ($i = 0; $i < 30; $i++) {
            $response = $this->postJson("/api/accounts/{$account->id}/action", [
                'action_type' => 'stop_production',
                'grid' => 10,
            ]);
            $this->assertNotEquals(429, $response->getStatusCode(), "Account action request #{$i} was unexpectedly rate limited.");
        }

        // 31st request should be throttled
        $responseThrottled = $this->postJson("/api/accounts/{$account->id}/action", [
            'action_type' => 'stop_production',
            'grid' => 10,
        ]);
        $this->assertEquals(429, $responseThrottled->getStatusCode(), 'Sensitive action exceeding 30/min was not throttled.');
    }

    public function test_invalid_region_is_rejected_on_account_creation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Unsupported region
        $responseInvalid = $this->postJson('/api/accounts', [
            'username' => 'bad_region_user',
            'password' => 'pass12345',
            'region' => 'unsupported_region_xyz',
        ]);

        $responseInvalid->assertStatus(422)
            ->assertJsonValidationErrors(['region']);

        // Supported region
        $responseValid = $this->postJson('/api/accounts', [
            'username' => 'good_region_user',
            'password' => 'pass12345',
            'region' => 'de',
        ]);

        $responseValid->assertStatus(201);
        $this->assertDatabaseHas('accounts', ['username' => 'good_region_user', 'region' => 'de']);
    }

    public function test_invalid_url_scheme_is_rejected_on_account_session_update(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'username' => 'session_test_user',
            'password' => 'secret123',
            'region' => 'ru',
            'bb_url' => 'https://example.com/play',
        ]);

        $this->actingAs($user);

        // Invalid non-http scheme (e.g. javascript or ftp or file)
        $responseInvalid = $this->putJson("/api/accounts/{$account->id}/session", [
            'dso_auth_token' => 'token123',
            'dso_auth_user' => 'user123',
            'bb_url' => 'ftp://malicious-server.local/exploit',
        ]);

        $responseInvalid->assertStatus(422)
            ->assertJsonValidationErrors(['bb_url']);

        // Valid http/https URL
        $responseValid = $this->putJson("/api/accounts/{$account->id}/session", [
            'dso_auth_token' => 'token123',
            'dso_auth_user' => 'user123',
            'bb_url' => 'https://www.thesettlersonline.ru/play',
        ]);

        $responseValid->assertStatus(200);
    }

    public function test_http_tso_client_rejects_unsupported_bb_url_scheme(): void
    {
        $account = new Account([
            'username' => 'test_scheme',
            'password' => 'pass',
            'region' => 'en',
            'bb_url' => 'ftp://bad-scheme.com',
            'dso_auth_user' => '12345',
            'dso_auth_token' => 'tok',
        ]);

        $client = app(HttpTsoClient::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid or unsupported URL scheme');

        $client->resolveServerUrl($account);
    }

    public function test_cookie_directory_is_created(): void
    {
        $account = new Account;
        $account->id = 9999;
        $account->username = 'cookie_test';

        $authService = app(TsoAuthService::class);
        $cookiePath = $authService->getCookieFile($account);

        $this->assertStringContainsString('account_9999.txt', $cookiePath);
        $this->assertTrue(is_dir(dirname($cookiePath)));
    }

    public function test_untrusted_host_header_is_rejected(): void
    {
        $response = $this->get('http://evil-attacker.com/');

        // Suspicious/untrusted host should result in 400 Bad Request
        $response->assertStatus(400);
    }

    public function test_client_cannot_spoof_client_ip_from_untrusted_source(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.1', // Public IP (not in trusted proxies)
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4',
        ])->get('/');

        // With untrusted REMOTE_ADDR, client IP remains 198.51.100.1 and does NOT become 1.2.3.4
        $this->assertEquals('198.51.100.1', $response->baseResponse->headers->get('X-Request-Id') ? request()->ip() : request()->ip());
    }
}
