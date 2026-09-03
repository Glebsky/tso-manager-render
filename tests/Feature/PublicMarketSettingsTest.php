<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicMarketSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_public_settings_endpoint_is_accessible_without_auth_and_returns_fallback_url(): void
    {
        config(['market.combat_simulator_url' => 'https://fallback-simulator.com']);

        $response = $this->getJson('/api/public/market/settings');

        $response->assertOk()
            ->assertExactJson([
                'combat_simulator_url' => 'https://fallback-simulator.com',
            ]);
    }

    public function test_public_settings_returns_configured_setting_when_present(): void
    {
        config(['market.combat_simulator_url' => 'https://fallback-simulator.com']);
        Setting::set('market_combat_simulator_url', 'https://custom-simulator.com');

        $response = $this->getJson('/api/public/market/settings');

        $response->assertOk()
            ->assertExactJson([
                'combat_simulator_url' => 'https://custom-simulator.com',
            ]);
    }

    public function test_public_settings_falls_back_when_database_value_is_empty(): void
    {
        config(['market.combat_simulator_url' => 'https://fallback-simulator.com']);
        Setting::set('market_combat_simulator_url', '');

        $response = $this->getJson('/api/public/market/settings');

        $response->assertOk()
            ->assertExactJson([
                'combat_simulator_url' => 'https://fallback-simulator.com',
            ]);
    }

    public function test_admin_can_update_combat_simulator_url_via_settings(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/settings', [
                'sync_interval' => 15,
                'log_retention_days' => 14,
                'combat_simulator_url' => 'https://updated-sim.example.com',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.combat_simulator_url', 'https://updated-sim.example.com');

        $publicResponse = $this->getJson('/api/public/market/settings');
        $publicResponse->assertOk()
            ->assertExactJson([
                'combat_simulator_url' => 'https://updated-sim.example.com',
            ]);
    }

    public function test_update_settings_rejects_invalid_combat_simulator_url(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/settings', [
                'sync_interval' => 15,
                'log_retention_days' => 14,
                'combat_simulator_url' => 'not-a-valid-url',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['combat_simulator_url']);
    }

    public function test_get_settings_includes_combat_simulator_url(): void
    {
        Setting::set('market_combat_simulator_url', 'https://current-sim.example.com');

        $response = $this->actingAs($this->user)
            ->getJson('/api/settings');

        $response->assertOk()
            ->assertJsonPath('data.combat_simulator_url', 'https://current-sim.example.com');
    }
}
