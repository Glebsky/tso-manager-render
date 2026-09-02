<?php

declare(strict_types=1);

namespace Tests\Feature\Market;

use App\Enums\MarketItemKind;
use App\Models\MarketOffer;
use App\Models\MarketServerConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferNamesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ru');

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
            'player_id' => 5001,
            'sender_name' => 'HeroPlayer',
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
    }

    public function test_no_raw_ids_in_response(): void
    {
        $response = $this->getJson('/api/public/market/analytics?server_id=ru');

        $response->assertOk();
        $offers = $response->json('active_offers');

        $this->assertNotEmpty($offers);
        $this->assertSame('Дикая Мери', $offers[0]['item_name']);
        $this->assertSame('adventure', $offers[0]['item_kind']);
        $this->assertSame('MadHenry', $offers[0]['item_subject']);
    }
}
