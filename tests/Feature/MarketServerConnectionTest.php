<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\MarketOffer;
use App\Models\MarketServerConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketServerConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_get_servers_returns_connections_and_accounts(): void
    {
        MarketServerConnection::create([
            'server_id' => 'ru_1',
            'locale' => 'RU',
            'display_name' => 'RU Server 1',
        ]);

        $response = $this->getJson('/api/market/servers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'servers',
                'accounts',
                'presets',
                'settings',
            ]);

        $this->assertNotEmpty($response->json('servers'));
    }

    public function test_store_server_creates_new_connection(): void
    {
        $account = Account::create([
            'username' => 'user_de@test.com',
            'password' => 'secret',
            'region' => 'de',
            'nickname' => 'DePlayer',
        ]);

        $response = $this->postJson('/api/market/servers', [
            'account_id' => $account->id,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('market_server_connections', [
            'server_id' => 'de',
            'locale' => 'DE',
            'account_id' => $account->id,
            'verification_status' => 'verified',
        ]);
    }

    public function test_verify_server_account_detects_mismatch(): void
    {
        $accountDe = Account::create([
            'username' => 'user_de@test.com',
            'password' => 'secret',
            'region' => 'de',
            'nickname' => 'DePlayer',
        ]);

        $connectionRu = MarketServerConnection::create([
            'server_id' => 'ru_main',
            'locale' => 'RU',
            'display_name' => 'RU Main',
            'account_id' => $accountDe->id,
        ]);

        $response = $this->postJson("/api/market/servers/{$connectionRu->id}/verify");

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'status' => 'mismatch',
            ]);

        $this->assertDatabaseHas('market_server_connections', [
            'id' => $connectionRu->id,
            'verification_status' => 'mismatch',
        ]);
    }

    public function test_multi_server_data_isolation(): void
    {
        // 1. Create server connections RU and DE
        MarketServerConnection::firstOrCreate(['server_id' => 'ru'], [
            'locale' => 'RU',
            'display_name' => 'RU Market',
        ]);

        MarketServerConnection::firstOrCreate(['server_id' => 'de'], [
            'locale' => 'DE',
            'display_name' => 'DE Market',
        ]);

        // 2. Insert offer for RU server
        MarketOffer::create([
            'server_id' => 'ru',
            'offer_id' => 1001,
            'player_id' => 1,
            'sender_name' => 'RuSeller',
            'item_id' => 'Oil',
            'item_name' => 'Oil',
            'amount' => 100,
            'target_item_id' => 'Coin',
            'target_item_name' => 'Coin',
            'target_amount' => 50,
            'price' => 0.5,
            'volume' => 1000,
            'lots_remaining' => 10,
            'created_at' => now(),
            'collected_at' => now(),
        ]);

        // Insert offer for DE server
        MarketOffer::create([
            'server_id' => 'de',
            'offer_id' => 2001,
            'player_id' => 2,
            'sender_name' => 'DeSeller',
            'item_id' => 'Bread',
            'item_name' => 'Bread',
            'amount' => 500,
            'target_item_id' => 'Water',
            'target_item_name' => 'Water',
            'target_amount' => 250,
            'price' => 0.5,
            'volume' => 5000,
            'lots_remaining' => 10,
            'created_at' => now(),
            'collected_at' => now(),
        ]);

        // 3. Fetch goods for RU server
        $ruGoodsRes = $this->getJson('/api/market/goods?server_id=ru');
        $ruGoodsRes->assertStatus(200);
        $ruGoods = $ruGoodsRes->json();
        $this->assertCount(1, $ruGoods);
        $this->assertEquals('Oil', $ruGoods[0]['item_id']);

        // Fetch goods for DE server
        $deGoodsRes = $this->getJson('/api/market/goods?server_id=de');
        $deGoodsRes->assertStatus(200);
        $deGoods = $deGoodsRes->json();
        $this->assertCount(1, $deGoods);
        $this->assertEquals('Bread', $deGoods[0]['item_id']);

        // 4. Analytics for RU server (should only return Oil offers, count 1)
        $ruAnalytics = $this->getJson('/api/market/analytics?server_id=ru');
        $ruAnalytics->assertStatus(200);
        $this->assertEquals(1, $ruAnalytics->json('total_active_count'));
        $this->assertEquals(1001, $ruAnalytics->json('active_offers.0.offer_id'));

        // Analytics for DE server (should only return Bread offers, count 1)
        $deAnalytics = $this->getJson('/api/market/analytics?server_id=de');
        $deAnalytics->assertStatus(200);
        $this->assertEquals(1, $deAnalytics->json('total_active_count'));
        $this->assertEquals(2001, $deAnalytics->json('active_offers.0.offer_id'));
    }

    public function test_multi_server_scheduler_command_continues_on_single_server_failure(): void
    {
        $accountRu = Account::create([
            'username' => 'ru_user@test.com',
            'password' => 'secret',
            'region' => 'ru',
        ]);

        $accountDe = Account::create([
            'username' => 'de_user@test.com',
            'password' => 'secret',
            'region' => 'de',
        ]);

        MarketServerConnection::updateOrCreate(
            ['server_id' => 'ru'],
            [
                'locale' => 'RU',
                'display_name' => 'RU Server',
                'account_id' => $accountRu->id,
                'sync_status' => 'connected',
                'last_synced_at' => now()->subHours(5),
            ]
        );

        MarketServerConnection::updateOrCreate(
            ['server_id' => 'de'],
            [
                'locale' => 'DE',
                'display_name' => 'DE Server',
                'account_id' => $accountDe->id,
                'sync_status' => 'connected',
                'last_synced_at' => now()->subHours(5),
            ]
        );

        // Run sync command --sync
        $this->artisan('tso:sync-market --sync')
            ->assertExitCode(0);
    }
}
