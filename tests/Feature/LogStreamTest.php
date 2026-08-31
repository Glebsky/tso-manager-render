<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LogLevel;
use App\Models\Account;
use App\Models\BotLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LogStreamTest extends TestCase
{
    use RefreshDatabase;

    private function createAccount(): Account
    {
        return Account::create([
            'username' => 'testuser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'testuser',
            'zone_data' => json_encode(['buildings' => []]),
        ]);
    }

    public function test_guest_cannot_access_log_stream(): void
    {
        Auth::logout();
        $this->app['session']->flush();

        $response = $this->json('GET', '/api/logs/stream');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_access_log_stream_headers(): void
    {
        $account = $this->createAccount();

        BotLog::create([
            'account_id' => $account->id,
            'level' => LogLevel::Info,
            'message' => 'Initial log message',
        ]);

        $response = $this->get('/api/logs/stream');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('Connection', 'keep-alive');
    }

    public function test_stream_outputs_logs_after_specified_id(): void
    {
        $account = $this->createAccount();

        $log1 = BotLog::create([
            'account_id' => $account->id,
            'level' => LogLevel::Info,
            'message' => 'First message',
        ]);

        $log2 = BotLog::create([
            'account_id' => $account->id,
            'level' => LogLevel::Warning,
            'message' => 'Second message',
        ]);

        $response = $this->get("/api/logs/stream?after_id={$log1->id}");

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString(': connected', $content);
        $this->assertStringContainsString("id: {$log2->id}", $content);
        $this->assertStringContainsString('Second message', $content);
        $this->assertStringNotContainsString('First message', $content);
    }
}
