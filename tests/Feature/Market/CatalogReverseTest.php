<?php

declare(strict_types=1);

namespace Tests\Feature\Market;

use App\Enums\MarketItemKind;
use App\Models\MarketOffer;
use App\Models\MarketServerConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogReverseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        MarketServerConnection::updateOrCreate(
            ['server_id' => 'ru'],
            [
                'locale' => 'RU',
                'display_name' => 'RU Server',
                'verification_status' => 'verified',
                'sync_status' => 'connected',
                'last_synced_at' => now(),
                'data_version' => 1,
            ]
        );

        MarketOffer::create([
            'server_id' => 'ru',
            'offer_id' => 2001,
            'player_id' => 1,
            'sender_name' => 'P1',
            'item_kind' => MarketItemKind::Resource,
            'item_id' => 'Coin',
            'item_name' => 'Монеты',
            'amount' => 1000,
            'target_item_kind' => MarketItemKind::Building,
            'target_item_id' => 'building:NobleResidence',
            'target_item_name' => 'Особняк',
            'target_amount' => 1,
            'price' => 0.001,
            'volume' => 1000,
            'lots_remaining' => 1,
            'created_at' => now(),
            'collected_at' => now(),
        ]);

        MarketOffer::create([
            'server_id' => 'ru',
            'offer_id' => 2002,
            'player_id' => 2,
            'sender_name' => 'P2',
            'item_kind' => MarketItemKind::Resource,
            'item_id' => 'Granite',
            'item_name' => 'Гранит',
            'amount' => 500,
            'target_item_kind' => MarketItemKind::Building,
            'target_item_id' => 'building:NobleResidence',
            'target_item_name' => 'Особняк',
            'target_amount' => 1,
            'price' => 0.002,
            'volume' => 500,
            'lots_remaining' => 1,
            'created_at' => now(),
            'collected_at' => now(),
        ]);
    }

    public function test_target_goods_returns_distinct_wanted_items(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/market/target-goods?server_id=ru');
        $response->assertOk();

        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $ids = array_column($data, 'item_id');
        $this->assertContains('building:NobleResidence', $ids);
    }

    public function test_sources_returns_items_offered_for_target(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/market/sources?server_id=ru&target_item_id=building:NobleResidence');
        $response->assertOk();

        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertCount(2, $data);

        $ids = array_column($data, 'target_item_id');
        $this->assertContains('Coin', $ids);
        $this->assertContains('Granite', $ids);
    }

    public function test_public_reverse_endpoints_accessible_without_auth(): void
    {
        $response = $this->getJson('/api/public/market/target-goods?server_id=ru');
        $response->assertOk();

        $responseSources = $this->getJson('/api/public/market/sources?server_id=ru&target_item_id=building:NobleResidence');
        $responseSources->assertOk();
    }

    public function test_bulk_payload_includes_target_goods_and_sources_map(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/market/bulk?server_id=ru');
        $response->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('target_goods', $data);
        $this->assertArrayHasKey('sources_map', $data);

        $targetGoods = $data['target_goods'];
        $ids = array_column($targetGoods, 'item_id');
        $this->assertContains('building:NobleResidence', $ids);

        $sourcesMap = $data['sources_map'];
        $this->assertArrayHasKey('building:NobleResidence', $sourcesMap);
        $sourceIds = array_column($sourcesMap['building:NobleResidence'], 'target_item_id');
        $this->assertContains('Coin', $sourceIds);
        $this->assertContains('Granite', $sourceIds);
    }
}
