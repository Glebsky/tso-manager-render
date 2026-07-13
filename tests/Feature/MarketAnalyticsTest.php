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
}
