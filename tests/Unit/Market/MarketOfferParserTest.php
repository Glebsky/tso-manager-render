<?php

declare(strict_types=1);

namespace Tests\Unit\Market;

use App\Services\Market\Sync\MarketOfferParser;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MarketOfferParserTest extends TestCase
{
    private MarketOfferParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('ru');
        $this->parser = app(MarketOfferParser::class);
    }

    public function test_adventure_row(): void
    {
        $now = Carbon::parse('2026-09-02 12:00:00');
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

        $result = $this->parser->parse($rawOffers, 'ru', $now);

        $this->assertSame(0, $result['unparsed_offers']);
        $this->assertCount(1, $result['offers']);
        $this->assertCount(1, $result['history']);

        $offer = $result['offers'][0];
        $this->assertSame(2001, $offer['offer_id']);
        $this->assertSame('HeroPlayer', $offer['sender_name']);
        $this->assertSame('adventure', $offer['item_kind']);
        $this->assertSame('adventure:MadHenry', $offer['item_id']);
        $this->assertSame('Дикая Мери', $offer['item_name']);
        $this->assertSame('MadHenry', $offer['item_subject']);
        $this->assertSame(0, $offer['amount']);
        $this->assertSame('resource', $offer['target_item_kind']);
        $this->assertSame('Coin', $offer['target_item_id']);
        $this->assertSame(150000, $offer['target_amount']);
        $this->assertSame(150000.0, $offer['price']);
        $this->assertSame(1, $offer['volume']);
        $this->assertSame(1, $offer['lots_remaining']);
        $this->assertSame(1, $offer['total_lots']);
    }

    public function test_resource_lot_is_byte_identical(): void
    {
        $now = Carbon::parse('2026-09-02 12:00:00');
        $rawOffers = [
            [
                'id' => 1001,
                'senderID' => 4242,
                'senderName' => 'PlayerOne',
                'type' => 0,
                'slotType' => 0,
                'created' => ((int) $now->timestamp) * 1000,
                'offer' => 'Marble,100|Coin,500|10',
                'lotsRemaining' => 5,
            ],
        ];

        $result = $this->parser->parse($rawOffers, 'ru', $now);

        $this->assertSame(0, $result['unparsed_offers']);
        $this->assertCount(1, $result['offers']);

        $offer = $result['offers'][0];
        $this->assertSame('ru', $offer['server_id']);
        $this->assertSame(1001, $offer['offer_id']);
        $this->assertSame(4242, $offer['player_id']);
        $this->assertSame('PlayerOne', $offer['sender_name']);
        $this->assertSame('resource', $offer['item_kind']);
        $this->assertSame('Marble', $offer['item_id']);
        $this->assertSame('Мрамор', $offer['item_name']);
        $this->assertNull($offer['item_subject']);
        $this->assertSame(100, $offer['amount']);
        $this->assertSame('resource', $offer['target_item_kind']);
        $this->assertSame('Coin', $offer['target_item_id']);
        $this->assertSame('Монеты', $offer['target_item_name']);
        $this->assertNull($offer['target_item_subject']);
        $this->assertSame(500, $offer['target_amount']);
        $this->assertSame(5.0, $offer['price']);
        $this->assertSame(500, $offer['volume']);
        $this->assertSame(5, $offer['lots_remaining']);
        $this->assertSame(0, $offer['trade_type']);
        $this->assertSame(0, $offer['slot_type']);
        $this->assertSame(10, $offer['total_lots']);
    }

    public function test_at_sign_lot_has_null_price(): void
    {
        $now = Carbon::parse('2026-09-02 12:00:00');
        $rawOffers = [
            [
                'id' => 2006,
                'senderID' => 5006,
                'senderName' => 'PriceLessBuyer',
                'type' => 0,
                'slotType' => 0,
                'created' => ((int) $now->timestamp) * 1000,
                'offer' => 'Marble,100|@|4',
                'lotsRemaining' => 4,
            ],
        ];

        $result = $this->parser->parse($rawOffers, 'ru', $now);

        $this->assertSame(0, $result['unparsed_offers']);
        $this->assertCount(1, $result['offers']);

        $offer = $result['offers'][0];
        $this->assertNull($offer['price']);
        $this->assertNull($offer['target_item_id']);
        $this->assertNull($offer['target_item_name']);
        $this->assertNull($offer['target_item_kind']);
        $this->assertNull($offer['target_item_subject']);
        $this->assertNull($offer['target_amount']);
    }

    public function test_unknown_trade_type_is_counted(): void
    {
        $now = Carbon::parse('2026-09-02 12:00:00');
        $rawOffers = [
            [
                'id' => 9991,
                'senderID' => 5091,
                'senderName' => 'BadTypePlayer',
                'type' => 7,
                'slotType' => 0,
                'created' => ((int) $now->timestamp) * 1000,
                'offer' => 'Marble,100|Coin,500|1',
                'lotsRemaining' => 1,
            ],
        ];

        $result = $this->parser->parse($rawOffers, 'ru', $now);

        $this->assertSame(1, $result['unparsed_offers']);
        $this->assertCount(0, $result['offers']);
        $this->assertCount(0, $result['history']);
    }

    public function test_expired_offer_not_in_active_offers_but_in_history(): void
    {
        $now = Carbon::parse('2026-09-02 12:00:00');
        $createdExpired = $now->copy()->subHours(8);

        $rawOffers = [
            [
                'id' => 3001,
                'senderID' => 5001,
                'senderName' => 'OldPlayer',
                'type' => 0,
                'slotType' => 0,
                'created' => ((int) $createdExpired->timestamp) * 1000,
                'offer' => 'Marble,100|Coin,500|1',
                'lotsRemaining' => 1,
            ],
        ];

        $result = $this->parser->parse($rawOffers, 'ru', $now);

        $this->assertSame(0, $result['unparsed_offers']);
        $this->assertCount(0, $result['offers']);
        $this->assertCount(1, $result['history']);
    }
}
