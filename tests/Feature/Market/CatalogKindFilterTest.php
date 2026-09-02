<?php

declare(strict_types=1);

namespace Tests\Feature\Market;

use App\Enums\MarketItemKind;
use App\Models\MarketOffer;
use App\Models\MarketServerConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogKindFilterTest extends TestCase
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
            'offer_id' => 1001,
            'player_id' => 1,
            'sender_name' => 'P1',
            'item_kind' => MarketItemKind::Resource,
            'item_id' => 'Marble',
            'item_name' => 'Мрамор',
            'amount' => 100,
            'target_item_kind' => MarketItemKind::Resource,
            'target_item_id' => 'Coin',
            'target_item_name' => 'Монеты',
            'target_amount' => 500,
            'price' => 5.0,
            'volume' => 500,
            'lots_remaining' => 5,
            'created_at' => now(),
            'collected_at' => now(),
        ]);

        MarketOffer::create([
            'server_id' => 'ru',
            'offer_id' => 1002,
            'player_id' => 2,
            'sender_name' => 'P2',
            'item_kind' => MarketItemKind::Adventure,
            'item_id' => 'adventure:MadHenry',
            'item_name' => 'Дикая Мери',
            'item_subject' => 'MadHenry',
            'amount' => 0,
            'target_item_kind' => MarketItemKind::Resource,
            'target_item_id' => 'Coin',
            'target_item_name' => 'Монеты',
            'target_amount' => 150000,
            'price' => 150000.0,
            'volume' => 1,
            'lots_remaining' => 1,
            'created_at' => now(),
            'collected_at' => now(),
        ]);

        MarketOffer::create([
            'server_id' => 'ru',
            'offer_id' => 1003,
            'player_id' => 3,
            'sender_name' => 'P3',
            'item_kind' => MarketItemKind::Buff,
            'item_id' => 'buff:FillDeposit:Fish',
            'item_name' => 'Пополнение залежи рыбы',
            'item_subject' => 'Fish',
            'amount' => 100,
            'target_item_kind' => MarketItemKind::Resource,
            'target_item_id' => 'Coin',
            'target_item_name' => 'Монеты',
            'target_amount' => 500,
            'price' => 500.0,
            'volume' => 1,
            'lots_remaining' => 1,
            'created_at' => now(),
            'collected_at' => now(),
        ]);
    }

    public function test_goods_kind_adventure(): void
    {
        $response = $this->getJson('/api/public/market/goods?server_id=ru&kind=adventure');

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(1, $data);
        $this->assertSame('adventure:MadHenry', $data[0]['item_id']);
        $this->assertSame('adventure', $data[0]['kind']);
    }

    public function test_goods_without_kind_returns_all_items(): void
    {
        $response = $this->getJson('/api/public/market/goods?server_id=ru');

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(3, $data);
        $itemIds = array_column($data, 'item_id');
        $this->assertContains('Marble', $itemIds);
        $this->assertContains('adventure:MadHenry', $itemIds);
        $this->assertContains('buff:FillDeposit:Fish', $itemIds);
    }

    public function test_invalid_kind_returns_422(): void
    {
        $response = $this->getJson('/api/public/market/goods?server_id=ru&kind=invalid_kind');

        $response->assertStatus(422);
    }

    public function test_different_kinds_have_isolated_cache(): void
    {
        $responseAll = $this->getJson('/api/public/market/goods?server_id=ru&kind=all');
        $responseAll->assertOk();
        $this->assertCount(3, $responseAll->json());

        $responseAdv = $this->getJson('/api/public/market/goods?server_id=ru&kind=adventure');
        $responseAdv->assertOk();
        $this->assertCount(1, $responseAdv->json());

        $responseRes = $this->getJson('/api/public/market/goods?server_id=ru&kind=resource');
        $responseRes->assertOk();
        $this->assertCount(1, $responseRes->json());
        $this->assertSame('Marble', $responseRes->json()[0]['item_id']);
    }

    public function test_popular_kind_filtering(): void
    {
        $response = $this->getJson('/api/public/market/popular?server_id=ru&kind=adventure');

        $response->assertOk();
    }
}
