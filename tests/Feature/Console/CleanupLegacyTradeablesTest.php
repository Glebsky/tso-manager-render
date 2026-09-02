<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\MarketItemKind;
use App\Models\MarketHistory;
use App\Models\MarketOffer;
use App\Models\MarketServerConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CleanupLegacyTradeablesTest extends TestCase
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
    }

    public function test_dry_run_does_not_delete_rows(): void
    {
        MarketOffer::create([
            'server_id' => 'ru',
            'offer_id' => 9001,
            'player_id' => 10,
            'sender_name' => 'PlayerLegacy',
            'item_kind' => MarketItemKind::Resource,
            'item_id' => 'Adventure',
            'item_name' => 'Приключение',
            'amount' => 1,
            'target_item_kind' => MarketItemKind::Resource,
            'target_item_id' => 'Coin',
            'target_item_name' => 'Монеты',
            'target_amount' => 100,
            'price' => 100.0,
            'volume' => 1,
            'lots_remaining' => 1,
            'created_at' => now(),
            'collected_at' => now(),
        ]);

        MarketHistory::create([
            'server_id' => 'ru',
            'offer_id' => 9001,
            'player_id' => 10,
            'item_kind' => MarketItemKind::Resource,
            'item_id' => 'Adventure',
            'item_name' => 'Приключение',
            'amount' => 1,
            'target_item_kind' => MarketItemKind::Resource,
            'target_item_id' => 'Coin',
            'target_item_name' => 'Монеты',
            'target_amount' => 100,
            'price' => 100.0,
            'volume' => 1,
            'collected_at' => now(),
        ]);

        $status = Artisan::call('market:cleanup-legacy-tradeables', ['--dry-run' => true]);
        $this->assertSame(0, $status);

        $this->assertSame(1, MarketOffer::where('item_id', 'Adventure')->count());
        $this->assertSame(1, MarketHistory::where('item_id', 'Adventure')->count());
    }

    public function test_cleanup_deletes_only_legacy_rows(): void
    {
        $legacyOffer = MarketOffer::create([
            'server_id' => 'ru',
            'offer_id' => 9002,
            'player_id' => 10,
            'sender_name' => 'PlayerLegacy',
            'item_kind' => MarketItemKind::Resource,
            'item_id' => 'Adventure',
            'item_name' => 'Приключение',
            'amount' => 1,
            'target_item_kind' => MarketItemKind::Resource,
            'target_item_id' => 'Coin',
            'target_item_name' => 'Монеты',
            'target_amount' => 100,
            'price' => 100.0,
            'volume' => 1,
            'lots_remaining' => 1,
            'created_at' => now(),
            'collected_at' => now(),
        ]);

        $validOffer = MarketOffer::create([
            'server_id' => 'ru',
            'offer_id' => 9003,
            'player_id' => 11,
            'sender_name' => 'PlayerValid',
            'item_kind' => MarketItemKind::Adventure,
            'item_id' => 'adventure:MadHenry',
            'item_name' => 'Дикая Мери',
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

        $status = Artisan::call('market:cleanup-legacy-tradeables');
        $this->assertSame(0, $status);

        $this->assertDatabaseMissing('market_offers', ['id' => $legacyOffer->id]);
        $this->assertDatabaseHas('market_offers', ['id' => $validOffer->id]);
    }
}
