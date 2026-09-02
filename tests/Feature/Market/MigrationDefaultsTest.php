<?php

declare(strict_types=1);

namespace Tests\Feature\Market;

use App\Enums\MarketItemKind;
use App\Models\MarketHistory;
use App\Models\MarketOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigrationDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_rows_get_resource_kind(): void
    {
        DB::table('market_offers')->insert([
            'server_id' => 'ru',
            'offer_id' => 12345,
            'player_id' => 999,
            'sender_name' => 'LegacyPlayer',
            'item_id' => 'Marble',
            'item_name' => 'Мрамор',
            'amount' => 100,
            'target_item_id' => 'Coin',
            'target_item_name' => 'Монеты',
            'target_amount' => 500,
            'price' => 5.0,
            'volume' => 500,
            'lots_remaining' => 5,
            'created_at' => now(),
            'collected_at' => now(),
        ]);

        $offer = MarketOffer::where('offer_id', 12345)->firstOrFail();

        $this->assertSame(MarketItemKind::Resource, $offer->item_kind);
        $this->assertNull($offer->item_subject);
        $this->assertNull($offer->target_item_kind);
    }

    public function test_market_history_supports_nullable_price_and_target(): void
    {
        $history = MarketHistory::create([
            'server_id' => 'ru',
            'offer_id' => 99999,
            'player_id' => 123,
            'item_kind' => MarketItemKind::Adventure,
            'item_id' => 'adventure:MadHenry',
            'item_name' => 'Дикая Мери',
            'item_subject' => 'MadHenry',
            'amount' => 0,
            'target_item_kind' => null,
            'target_item_id' => null,
            'target_item_name' => null,
            'target_item_subject' => null,
            'target_amount' => null,
            'price' => null,
            'volume' => 1,
            'trade_type' => 2,
            'slot_type' => 0,
            'total_lots' => 1,
            'collected_at' => now(),
        ]);

        $this->assertGreaterThan(0, $history->id);
        $this->assertNull($history->price);
        $this->assertSame(MarketItemKind::Adventure, $history->item_kind);
    }
}
