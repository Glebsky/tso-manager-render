<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Storage;

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

        Storage::disk('local')->assertExists('market_settings.json');
        
        $settings = json_decode(Storage::disk('local')->get('market_settings.json'), true);
        $this->assertEquals('30', $settings['sync_interval']);
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
                     'active_offers'
                 ]);

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
                         'average_price',
                         'min_price',
                         'max_price',
                     ],
                     'current_price',
                     'history',
                     'mirrored_stats',
                     'mirrored_current',
                     'mirrored_history'
                 ]);

        // Average should be (0.5 + 0.6) / 2 = 0.55
        $this->assertEquals(0.55, $response->json('stats.average_price'));
        $this->assertEquals(0.5, $response->json('stats.min_price'));
        $this->assertEquals(0.6, $response->json('stats.max_price'));
        
        // Current should be the latest recorded price in history (0.5 was collected 1 hour ago, which is newer than 2 hours ago)
        $this->assertEquals(0.5, $response->json('current_price'));
    }
}
