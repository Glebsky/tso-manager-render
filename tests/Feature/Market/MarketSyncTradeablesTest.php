<?php

declare(strict_types=1);

namespace Tests\Feature\Market;

use App\Enums\MarketItemKind;
use App\Models\MarketOffer;
use App\Services\Market\Sync\MarketOfferParser;
use App\Services\Market\Sync\MarketOfferPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MarketSyncTradeablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unparsed_offers_are_not_persisted(): void
    {
        app()->setLocale('ru');
        $fixturePath = base_path('tests/Fixtures/Market/offers_tradeables.json');
        $rawOffers = json_decode((string) file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);

        $now = Carbon::createFromTimestamp(1756704000)->addMinutes(10);
        Carbon::setTestNow($now);

        $parser = app(MarketOfferParser::class);
        $persister = app(MarketOfferPersister::class);

        $parsed = $parser->parse($rawOffers, 'ru', $now);

        // We had 2 invalid items in fixtures (id 9991 and 9992)
        $this->assertSame(2, $parsed['unparsed_offers']);
        $this->assertCount(10, $parsed['offers']);

        $persister->persist('ru', $parsed['offers'], $parsed['history']);

        $this->assertDatabaseMissing('market_offers', ['offer_id' => 9991]);
        $this->assertDatabaseMissing('market_offers', ['offer_id' => 9992]);
        $this->assertSame(10, MarketOffer::where('server_id', 'ru')->count());

        $adventureOffer = MarketOffer::where('offer_id', 2001)->firstOrFail();
        $this->assertSame(MarketItemKind::Adventure, $adventureOffer->item_kind);
        $this->assertSame('adventure:MadHenry', $adventureOffer->item_id);
        $this->assertSame('Дикая Мери', $adventureOffer->item_name);
        $this->assertSame(150000.0, $adventureOffer->price);

        $priceLessOffer = MarketOffer::where('offer_id', 2006)->firstOrFail();
        $this->assertNull($priceLessOffer->price);
        $this->assertNull($priceLessOffer->target_item_id);
    }

    public function test_repeated_sync_updates_item_kind_and_new_columns(): void
    {
        app()->setLocale('ru');
        $now = Carbon::createFromTimestamp(1756704000)->addMinutes(10);
        Carbon::setTestNow($now);

        // Pre-seed legacy record with 'resource' kind
        MarketOffer::create([
            'server_id' => 'ru',
            'offer_id' => 2001,
            'player_id' => 5001,
            'sender_name' => 'HeroPlayer',
            'item_kind' => MarketItemKind::Resource,
            'item_id' => 'Adventure',
            'item_name' => 'Adventure',
            'amount' => 0,
            'target_item_kind' => MarketItemKind::Resource,
            'target_item_id' => 'Coin',
            'target_item_name' => 'Coins',
            'target_amount' => 150000,
            'price' => 150000.0,
            'volume' => 1,
            'lots_remaining' => 1,
            'created_at' => $now,
            'collected_at' => $now,
        ]);

        $rawOffers = [
            [
                'id' => 2001,
                'senderID' => 5001,
                'senderName' => 'HeroPlayer',
                'type' => 2,
                'slotType' => 0,
                'created' => ((int) $now->timestamp) * 1000,
                'offer' => 'Adventure,MadHenry,0|Coin,150000|1',
                'lotsRemaining' => 1,
            ],
        ];

        $parser = app(MarketOfferParser::class);
        $persister = app(MarketOfferPersister::class);

        $parsed = $parser->parse($rawOffers, 'ru', $now);
        $persister->persist('ru', $parsed['offers'], $parsed['history']);

        $updated = MarketOffer::where('offer_id', 2001)->firstOrFail();
        $this->assertSame(MarketItemKind::Adventure, $updated->item_kind);
        $this->assertSame('adventure:MadHenry', $updated->item_id);
        $this->assertSame('Дикая Мери', $updated->item_name);
        $this->assertSame('MadHenry', $updated->item_subject);
    }
}
