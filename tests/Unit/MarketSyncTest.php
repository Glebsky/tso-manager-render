<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\MarketHistory;
use App\Models\MarketOffer;
use App\Services\Market\Sync\MarketOfferParser;
use App\Services\Market\Sync\MarketOfferPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_market_offer_parser_transforms_raw_strings(): void
    {
        $parser = app(MarketOfferParser::class);

        $rawOffers = [
            [
                'id' => 101,
                'senderID' => 50,
                'senderName' => 'TraderBob',
                'type' => 0,
                'offer' => 'Oil,100|Coin,50|2',
                'lotsRemaining' => 2,
                'created' => (int) now()->timestamp * 1000,
            ],
            [
                // Invalid offer string - ignored
                'id' => 102,
                'type' => 0,
                'offer' => 'InvalidString',
            ],
            [
                // Free gift target - parsed with null price
                'id' => 103,
                'type' => 0,
                'offer' => 'Oil,100|@|1',
                'lotsRemaining' => 1,
                'created' => (int) now()->timestamp * 1000,
            ],
        ];

        $result = $parser->parse($rawOffers, 'ru_evelans', now());

        $this->assertCount(2, $result['offers']);
        $this->assertCount(2, $result['history']);

        $offer = $result['offers'][0];
        $this->assertEquals(101, $offer['offer_id']);
        $this->assertEquals('ru_evelans', $offer['server_id']);
        $this->assertEquals(50, $offer['player_id']);
        $this->assertEquals('TraderBob', $offer['sender_name']);
        $this->assertEquals('Oil', $offer['item_id']);
        $this->assertEquals('Oil', $offer['item_name']);
        $this->assertEquals(100, $offer['amount']);
        $this->assertEquals('Coin', $offer['target_item_id']);
        $this->assertEquals('Coins', $offer['target_item_name']);
        $this->assertEquals(50, $offer['target_amount']);
        $this->assertEquals(0.5, $offer['price']);
        $this->assertEquals(200, $offer['volume']);
    }

    public function test_market_offer_persister_inserts_and_deduplicates_history(): void
    {
        $persister = new MarketOfferPersister;

        $serverId = 'ru_evelans';

        // Pre-seed an existing history entry
        MarketHistory::create([
            'server_id' => $serverId,
            'offer_id' => 201,
            'player_id' => 10,
            'item_id' => 'Wood',
            'item_name' => 'Wood',
            'amount' => 100,
            'target_item_id' => 'Stone',
            'target_item_name' => 'Stone',
            'target_amount' => 50,
            'price' => 0.5,
            'volume' => 100,
            'collected_at' => now(),
        ]);

        $offers = [
            [
                'server_id' => $serverId,
                'offer_id' => 201,
                'player_id' => 10,
                'sender_name' => 'WoodTrader',
                'item_id' => 'Wood',
                'item_name' => 'Wood',
                'amount' => 100,
                'target_item_id' => 'Stone',
                'target_item_name' => 'Stone',
                'target_amount' => 50,
                'price' => 0.5,
                'volume' => 100,
                'lots_remaining' => 1,
                'created_at' => now()->toDateTimeString(),
                'collected_at' => now()->toDateTimeString(),
            ],
            [
                'server_id' => $serverId,
                'offer_id' => 202,
                'player_id' => 11,
                'sender_name' => 'IronTrader',
                'item_id' => 'Iron',
                'item_name' => 'Iron',
                'amount' => 10,
                'target_item_id' => 'Gold',
                'target_item_name' => 'Gold',
                'target_amount' => 20,
                'price' => 2.0,
                'volume' => 10,
                'lots_remaining' => 1,
                'created_at' => now()->toDateTimeString(),
                'collected_at' => now()->toDateTimeString(),
            ],
        ];

        $history = [
            [
                'server_id' => $serverId,
                'offer_id' => 201, // Already exists, should be deduplicated
                'player_id' => 10,
                'item_id' => 'Wood',
                'item_name' => 'Wood',
                'amount' => 100,
                'target_item_id' => 'Stone',
                'target_item_name' => 'Stone',
                'target_amount' => 50,
                'price' => 0.5,
                'volume' => 100,
                'collected_at' => now()->toDateTimeString(),
            ],
            [
                'server_id' => $serverId,
                'offer_id' => 202, // New history entry
                'player_id' => 11,
                'item_id' => 'Iron',
                'item_name' => 'Iron',
                'amount' => 10,
                'target_item_id' => 'Gold',
                'target_item_name' => 'Gold',
                'target_amount' => 20,
                'price' => 2.0,
                'volume' => 10,
                'collected_at' => now()->toDateTimeString(),
            ],
        ];

        $persister->persist($serverId, $offers, $history);

        $this->assertEquals(2, MarketOffer::where('server_id', $serverId)->count());
        // Should have 2 history entries (1 pre-seeded + 1 new, duplicate 201 ignored)
        $this->assertEquals(2, MarketHistory::where('server_id', $serverId)->count());
    }

    public function test_market_offer_persister_upserts_and_removes_stale_offers(): void
    {
        $persister = new MarketOfferPersister;
        $serverId = 'ru_evelans';

        // Pre-seed an offer that will become stale and an offer that will be updated
        MarketOffer::create([
            'server_id' => $serverId,
            'offer_id' => 301,
            'player_id' => 10,
            'sender_name' => 'OldTrader',
            'item_id' => 'Wood',
            'item_name' => 'Wood',
            'amount' => 100,
            'target_item_id' => 'Stone',
            'target_item_name' => 'Stone',
            'target_amount' => 50,
            'price' => 0.5,
            'volume' => 100,
            'lots_remaining' => 1,
            'created_at' => now()->subHours(2),
            'collected_at' => now()->subHours(1),
        ]);

        MarketOffer::create([
            'server_id' => $serverId,
            'offer_id' => 302,
            'player_id' => 11,
            'sender_name' => 'OldTrader2',
            'item_id' => 'Iron',
            'item_name' => 'Iron',
            'amount' => 10,
            'target_item_id' => 'Gold',
            'target_item_name' => 'Gold',
            'target_amount' => 20,
            'price' => 2.0,
            'volume' => 10,
            'lots_remaining' => 1,
            'created_at' => now()->subHours(2),
            'collected_at' => now()->subHours(1),
        ]);

        $newSyncTime = now();

        // 301 is updated, 303 is new, 302 is missing (stale)
        $offers = [
            [
                'server_id' => $serverId,
                'offer_id' => 301,
                'player_id' => 10,
                'sender_name' => 'OldTrader',
                'item_id' => 'Wood',
                'item_name' => 'Wood',
                'amount' => 200,
                'target_item_id' => 'Stone',
                'target_item_name' => 'Stone',
                'target_amount' => 100,
                'price' => 0.5,
                'volume' => 200,
                'lots_remaining' => 2,
                'created_at' => $newSyncTime->toDateTimeString(),
                'collected_at' => $newSyncTime->toDateTimeString(),
            ],
            [
                'server_id' => $serverId,
                'offer_id' => 303,
                'player_id' => 12,
                'sender_name' => 'NewTrader',
                'item_id' => 'Tool',
                'item_name' => 'Tool',
                'amount' => 50,
                'target_item_id' => 'Coin',
                'target_item_name' => 'Coins',
                'target_amount' => 25,
                'price' => 0.5,
                'volume' => 50,
                'lots_remaining' => 1,
                'created_at' => $newSyncTime->toDateTimeString(),
                'collected_at' => $newSyncTime->toDateTimeString(),
            ],
        ];

        // Re-persisting should not throw unique constraint violation, should update 301, add 303, remove 302
        $persister->persist($serverId, $offers, []);

        $this->assertEquals(2, MarketOffer::where('server_id', $serverId)->count());
        $this->assertDatabaseMissing('market_offers', ['server_id' => $serverId, 'offer_id' => 302]);
        $this->assertDatabaseHas('market_offers', [
            'server_id' => $serverId,
            'offer_id' => 301,
            'amount' => 200,
            'lots_remaining' => 2,
        ]);
        $this->assertDatabaseHas('market_offers', [
            'server_id' => $serverId,
            'offer_id' => 303,
            'amount' => 50,
        ]);
    }

    public function test_market_offer_persister_clears_all_offers_when_empty(): void
    {
        $persister = new MarketOfferPersister;
        $serverId = 'ru_evelans';

        MarketOffer::create([
            'server_id' => $serverId,
            'offer_id' => 401,
            'player_id' => 10,
            'sender_name' => 'Trader',
            'item_id' => 'Wood',
            'item_name' => 'Wood',
            'amount' => 100,
            'target_item_id' => 'Stone',
            'target_item_name' => 'Stone',
            'target_amount' => 50,
            'price' => 0.5,
            'volume' => 100,
            'lots_remaining' => 1,
            'created_at' => now(),
            'collected_at' => now(),
        ]);

        $persister->persist($serverId, [], []);

        $this->assertEquals(0, MarketOffer::where('server_id', $serverId)->count());
    }
}
