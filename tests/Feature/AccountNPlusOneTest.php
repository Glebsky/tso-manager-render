<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountNPlusOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetching_account_list_uses_constant_queries(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        for ($i = 1; $i <= 10; $i++) {
            Account::create([
                'username' => "user_{$i}",
                'nickname' => "Player_{$i}",
                'password' => 'secret123',
                'server' => 'ru_1',
                'region' => 'ru',
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($user)->getJson('/api/accounts');

        $response->assertOk();
        $queries = DB::getQueryLog();

        // Exactly 1 SQL query for fetching 10 accounts (with select/exists check for market connections)
        $this->assertCount(1, $queries, 'Fetching account list should execute a single query without per-row N+1 queries.');
    }
}
