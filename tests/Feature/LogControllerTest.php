<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\BotLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_index_returns_paginated_logs_and_accounts(): void
    {
        $user = User::factory()->create();

        $account = Account::create([
            'username' => 'log_user',
            'email' => 'log_user@example.com',
            'password' => 'secret',
            'is_active' => true,
        ]);

        BotLog::create([
            'account_id' => $account->id,
            'level' => 'info',
            'message' => 'Test log entry 1',
        ]);

        BotLog::create([
            'account_id' => $account->id,
            'level' => 'error',
            'message' => 'Test log entry 2',
        ]);

        $response = $this->actingAs($user)->getJson('/api/logs');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'links',
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
            'accounts',
        ]);

        $this->assertCount(2, $response->json('data'));
        $this->assertEquals(1, $response->json('meta.current_page'));
    }

    public function test_logs_index_filters_by_account_and_level(): void
    {
        $user = User::factory()->create();

        $account1 = Account::create([
            'username' => 'user1',
            'email' => 'user1@example.com',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $account2 = Account::create([
            'username' => 'user2',
            'email' => 'user2@example.com',
            'password' => 'secret',
            'is_active' => true,
        ]);

        BotLog::create([
            'account_id' => $account1->id,
            'level' => 'info',
            'message' => 'Info log for user 1',
        ]);

        BotLog::create([
            'account_id' => $account1->id,
            'level' => 'error',
            'message' => 'Error log for user 1',
        ]);

        BotLog::create([
            'account_id' => $account2->id,
            'level' => 'error',
            'message' => 'Error log for user 2',
        ]);

        $response = $this->actingAs($user)->getJson('/api/logs?account_id='.$account1->id.'&level=error');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals('Error log for user 1', $response->json('data.0.message'));
    }
}
