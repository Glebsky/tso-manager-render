<?php

namespace Tests\Feature;

use App\Models\MarketHistory;
use App\Models\MarketOffer;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_get_settings_returns_default_settings(): void
    {
        $response = $this->getJson('/api/market/settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'settings' => [
                    'account_id',
                    'sync_interval',
                    'custom_interval_minutes',
                ],
                'accounts',
                'connection_status',
                'last_sync',
            ]);
    }

    public function test_update_settings_validates_input(): void
    {
        $response = $this->putJson('/api/market/settings', [
            'sync_interval' => 'invalid_interval',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sync_interval']);
    }

    public function test_update_settings_saves_correct_data(): void
    {
        $response = $this->putJson('/api/market/settings', [
            'account_id' => null,
            'sync_interval' => '30',
            'custom_interval_minutes' => 15,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertEquals('30', Setting::get('market_sync_interval'));
    }

    public function test_get_analytics_returns_historical_and_active_data(): void
    {
        // 1. Seed some active and closed offers
        // Active offer
        \App\Models\MarketOffer::create([
            'offer_id' => 101,
            'player_id' => 1,
            'sender_name' => 'Seller1',
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

        // Historical offer (already closed/inactive, i.e. NOT in MarketOffer)
        \App\Models\MarketHistory::create([
            'offer_id' => 102,
            'player_id' => 2,
            'item_id' => 'Oil',
            'item_name' => 'Oil',
            'amount' => 100,
            'target_item_id' => 'Coin',
            'target_item_name' => 'Coin',
            'target_amount' => 60,
            'price' => 0.6,
            'volume' => 2000,
            'collected_at' => now()->subHours(2),
        ]);

        // Add active to history as well (normally done during sync)
        \App\Models\MarketHistory::create([
            'offer_id' => 101,
            'player_id' => 1,
            'item_id' => 'Oil',
            'item_name' => 'Oil',
            'amount' => 100,
            'target_item_id' => 'Coin',
            'target_item_name' => 'Coin',
            'target_amount' => 50,
            'price' => 0.5,
            'volume' => 1000,
            'collected_at' => now()->subHour(),
        ]);

        // 2. Fetch overview (no item_id selected)
        $response = $this->getJson('/api/market/analytics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'popular',
                'active_offers',
                'total_active_count',
            ]);

        $this->assertEquals(1, $response->json('total_active_count'));

        // Assert popular items come from MarketHistory (includes closed oil and active oil)
        $popular = $response->json('popular');
        $this->assertNotEmpty($popular);
        $this->assertEquals('Oil', $popular[0]['item_id']);
        // 2 separate offers logged in MarketHistory for Oil
        $this->assertEquals(2, $popular[0]['offers_count']);

        // Assert active offers only contains current active ones (MarketOffer)
        $activeOffers = $response->json('active_offers');
        $this->assertCount(1, $activeOffers);
        $this->assertEquals(101, $activeOffers[0]['offer_id']);

        // 3. Fetch analytics for Oil -> Coin
        $response = $this->getJson('/api/market/analytics?item_id=Oil&target_item_id=Coin');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'stats' => [
                    'average',
                    'minimum',
                    'maximum',
                    'current',
                ],
                'history',
                'mirrored_stats',
                'mirrored_history',
            ]);

        // Average should be (0.5 + 0.6) / 2 = 0.55
        $this->assertEquals(0.55, $response->json('stats.average'));
        $this->assertEquals(0.5, $response->json('stats.minimum'));
        $this->assertEquals(0.6, $response->json('stats.maximum'));

        // Current should be the latest recorded price in history (0.5 was collected 1 hour ago, which is newer than 2 hours ago)
        $this->assertEquals(0.5, $response->json('stats.current'));
    }

    public function test_get_arbitrage_finds_profitable_loops()
    {
        MarketOffer::truncate();

        // 1. Create a 2-step loop: Iron_Ore <-> Steel_Swords
        // Offer A: Sell 1000 Steel_Swords for 500 Iron_Ore (lots: 10)
        MarketOffer::create([
            'offer_id' => 201,
            'player_id' => 1,
            'sender_name' => 'SellerA',
            'item_id' => 'Steel_Swords',
            'item_name' => 'Steel Swords',
            'amount' => 1000,
            'target_item_id' => 'Iron_Ore',
            'target_item_name' => 'Iron Ore',
            'target_amount' => 500,
            'price' => 0.5,
            'volume' => 10000,
            'lots_remaining' => 10,
            'created_at' => now(),
            'collected_at' => now(),
        ]);

        // Offer B: Sell 1000 Iron_Ore for 1500 Steel_Swords (lots: 5)
        MarketOffer::create([
            'offer_id' => 202,
            'player_id' => 2,
            'sender_name' => 'SellerB',
            'item_id' => 'Iron_Ore',
            'item_name' => 'Iron Ore',
            'amount' => 1000,
            'target_item_id' => 'Steel_Swords',
            'target_item_name' => 'Steel Swords',
            'target_amount' => 1500,
            'price' => 1.5,
            'volume' => 5000,
            'lots_remaining' => 5,
            'created_at' => now(),
            'collected_at' => now(),
        ]);

        $response = $this->getJson('/api/market/arbitrage');

        $response->assertStatus(200);
        $loops = $response->json();

        $this->assertNotEmpty($loops);

        // Loop 0 (Steel_Swords -> Iron_Ore -> Steel_Swords)
        $this->assertEquals('2-step', $loops[0]['type']);
        $this->assertEquals('Steel_Swords', $loops[0]['start_resource']);
        $this->assertEquals(2500, $loops[0]['profit']['amount']);
        $this->assertEquals(5, $loops[0]['steps'][0]['lots']);
        $this->assertEquals(10, $loops[0]['steps'][1]['lots']);

        // Loop 1 (Iron_Ore -> Steel_Swords -> Iron_Ore)
        $this->assertEquals('2-step', $loops[1]['type']);
        $this->assertEquals('Iron_Ore', $loops[1]['start_resource']);
        $this->assertEquals(1000, $loops[1]['profit']['amount']);
        $this->assertEquals(6, $loops[1]['steps'][0]['lots']);
        $this->assertEquals(4, $loops[1]['steps'][1]['lots']);
    }

    public function test_expired_offers_are_filtered_out_from_active_listings()
    {
        MarketOffer::truncate();

        // 1. Create a non-expired active offer (5 hours ago)
        MarketOffer::create([
            'offer_id' => 301,
            'player_id' => 1,
            'sender_name' => 'FreshSeller',
            'item_id' => 'Oil',
            'item_name' => 'Oil',
            'amount' => 100,
            'target_item_id' => 'Coin',
            'target_item_name' => 'Coin',
            'target_amount' => 50,
            'price' => 0.5,
            'volume' => 1000,
            'lots_remaining' => 10,
            'created_at' => now()->subHours(5),
            'collected_at' => now(),
        ]);

        // 2. Create an expired active offer (7 hours ago)
        MarketOffer::create([
            'offer_id' => 302,
            'player_id' => 2,
            'sender_name' => 'ExpiredSeller',
            'item_id' => 'Oil',
            'item_name' => 'Oil',
            'amount' => 100,
            'target_item_id' => 'Coin',
            'target_item_name' => 'Coin',
            'target_amount' => 60,
            'price' => 0.6,
            'volume' => 2000,
            'lots_remaining' => 5,
            'created_at' => now()->subHours(7),
            'collected_at' => now()->subHours(7),
        ]);

        $response = $this->getJson('/api/market/analytics');

        $response->assertStatus(200);

        $activeOffers = $response->json('active_offers');
        // Only 301 should be returned, 302 should be filtered out
        $this->assertCount(1, $activeOffers);
        $this->assertEquals(301, $activeOffers[0]['offer_id']);
        $this->assertEquals(1, $response->json('total_active_count'));
    }

    public function test_public_market_api_endpoints_are_accessible_without_auth(): void
    {
        MarketOffer::create([
            'offer_id' => 401,
            'player_id' => 1,
            'sender_name' => 'PublicSeller',
            'item_id' => 'Bread',
            'item_name' => 'Bread',
            'amount' => 100,
            'target_item_id' => 'Water',
            'target_item_name' => 'Water',
            'target_amount' => 50,
            'price' => 0.5,
            'volume' => 1000,
            'lots_remaining' => 10,
            'created_at' => now(),
            'collected_at' => now(),
        ]);

        $this->getJson('/api/public/market/goods')->assertStatus(200);
        $this->getJson('/api/public/market/targets?item_id=Bread')->assertStatus(200);
        $this->getJson('/api/public/market/popular?period=1d')->assertStatus(200);
        $this->getJson('/api/public/market/analytics')->assertStatus(200);
        $this->getJson('/api/public/market/arbitrage')->assertStatus(200);
        $this->getJson('/api/public/market/bulk')->assertStatus(200);
    }

    public function test_get_bulk_returns_complete_market_payload(): void
    {
        MarketOffer::create([
            'offer_id' => 501,
            'player_id' => 1,
            'sender_name' => 'BulkSeller',
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

        MarketHistory::create([
            'offer_id' => 501,
            'player_id' => 1,
            'item_id' => 'Oil',
            'item_name' => 'Oil',
            'amount' => 100,
            'target_item_id' => 'Coin',
            'target_item_name' => 'Coin',
            'target_amount' => 50,
            'price' => 0.5,
            'volume' => 1000,
            'collected_at' => now()->subHour(),
        ]);

        $response = $this->getJson('/api/market/bulk');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'server_id',
                'cache_ttl_seconds',
                'next_sync_at',
                'data_version',
                'goods',
                'targets_map',
                'popular',
                'active_offers',
                'total_active_count',
                'arbitrage',
                'pairs',
            ]);

        $this->assertNotEmpty($response->json('goods'));
        $this->assertArrayHasKey('Oil', $response->json('targets_map'));
        $this->assertArrayHasKey('Oil|Coin', $response->json('pairs'));
    }
}
