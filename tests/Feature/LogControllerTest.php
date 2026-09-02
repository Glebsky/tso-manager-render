<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LogLevel;
use App\Models\Account;
use App\Models\BotLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LogControllerTest extends TestCase
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

    public function test_guest_cannot_access_logs(): void
    {
        Auth::logout();
        $this->app['session']->flush();

        $response = $this->json('GET', '/api/logs');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_paginated_logs(): void
    {
        $account = $this->createAccount();

        BotLog::create([
            'account_id' => $account->id,
            'level' => LogLevel::Info,
            'message' => 'First message',
        ]);

        BotLog::create([
            'account_id' => $account->id,
            'level' => LogLevel::Warning,
            'message' => 'Second message',
        ]);

        $response = $this->getJson('/api/logs');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'account_id', 'level', 'message', 'created_at', 'account'],
            ],
            'accounts',
            'meta' => ['server_time'],
        ]);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_filter_logs_by_account_and_level(): void
    {
        $account1 = $this->createAccount();
        $account2 = Account::create([
            'username' => 'seconduser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'seconduser',
            'zone_data' => json_encode(['buildings' => []]),
        ]);

        BotLog::create([
            'account_id' => $account1->id,
            'level' => LogLevel::Info,
            'message' => 'Account 1 info',
        ]);

        BotLog::create([
            'account_id' => $account2->id,
            'level' => LogLevel::Error,
            'message' => 'Account 2 error',
        ]);

        $response = $this->getJson("/api/logs?account_id={$account2->id}&level=error");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Account 2 error', $data[0]['message']);
    }
}
