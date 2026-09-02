<?php

declare(strict_types=1);

namespace Tests\Unit\Market;

use App\Enums\MarketItemKind;
use App\Services\Market\Tradeables\TradeOfferDecoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TradeOfferDecoderTest extends TestCase
{
    private TradeOfferDecoder $decoder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->decoder = new TradeOfferDecoder;
    }

    public function test_adventure_offer_side(): void
    {
        $decoded = $this->decoder->decode('Adventure,MadHenry,0|Coin,150000|1', TradeOfferDecoder::TRADE_BUFF_FOR_RES);

        $this->assertNotNull($decoded);
        $this->assertSame(MarketItemKind::Adventure, $decoded->offer->kind);
        $this->assertSame('Adventure', $decoded->offer->baseName);
        $this->assertSame('MadHenry', $decoded->offer->subject);
        $this->assertSame(0, $decoded->offer->rawAmount);
        $this->assertSame(1, $decoded->offer->units);
        $this->assertNull($decoded->offer->recurringChance);

        $this->assertNotNull($decoded->costs);
        $this->assertSame(MarketItemKind::Resource, $decoded->costs->kind);
        $this->assertSame('Coin', $decoded->costs->baseName);
        $this->assertNull($decoded->costs->subject);
        $this->assertSame(150000, $decoded->costs->rawAmount);
        $this->assertSame(150000, $decoded->costs->units);

        $this->assertSame(1, $decoded->totalLots);
        $this->assertSame(TradeOfferDecoder::TRADE_BUFF_FOR_RES, $decoded->tradeType);
    }

    public function test_buff_with_subject(): void
    {
        $decoded = $this->decoder->decode('FillDeposit,Fish,100|Coin,500|1', TradeOfferDecoder::TRADE_BUFF_FOR_RES);

        $this->assertNotNull($decoded);
        $this->assertSame(MarketItemKind::Buff, $decoded->offer->kind);
        $this->assertSame('FillDeposit', $decoded->offer->baseName);
        $this->assertSame('Fish', $decoded->offer->subject);
        $this->assertSame(100, $decoded->offer->rawAmount);
        $this->assertSame(1, $decoded->offer->units);
    }

    public function test_adventure_on_cost_side(): void
    {
        $decoded = $this->decoder->decode('Coin,150000|Adventure,MadHenry,0|1', TradeOfferDecoder::TRADE_RES_FOR_BUFF);

        $this->assertNotNull($decoded);
        $this->assertSame(MarketItemKind::Resource, $decoded->offer->kind);
        $this->assertSame('Coin', $decoded->offer->baseName);
        $this->assertSame(150000, $decoded->offer->units);

        $this->assertNotNull($decoded->costs);
        $this->assertSame(MarketItemKind::Adventure, $decoded->costs->kind);
        $this->assertSame('Adventure', $decoded->costs->baseName);
        $this->assertSame('MadHenry', $decoded->costs->subject);
        $this->assertSame(1, $decoded->costs->units);
    }

    public function test_buff_without_subject(): void
    {
        $decoded = $this->decoder->decode('ProductivityBuffLvl3,,1|Coin,5000|1', TradeOfferDecoder::TRADE_BUFF_FOR_RES);

        $this->assertNotNull($decoded);
        $this->assertSame(MarketItemKind::Buff, $decoded->offer->kind);
        $this->assertSame('ProductivityBuffLvl3', $decoded->offer->baseName);
        $this->assertNull($decoded->offer->subject);
        $this->assertSame(1, $decoded->offer->rawAmount);
        $this->assertSame(1, $decoded->offer->units);
    }

    public function test_at_sign_on_cost_side(): void
    {
        $decoded = $this->decoder->decode('Marble,100|@|4', TradeOfferDecoder::TRADE_RES_FOR_RES);

        $this->assertNotNull($decoded);
        $this->assertSame(MarketItemKind::Resource, $decoded->offer->kind);
        $this->assertSame('Marble', $decoded->offer->baseName);
        $this->assertSame(100, $decoded->offer->units);
        $this->assertNull($decoded->costs);
        $this->assertSame(4, $decoded->totalLots);
    }

    public function test_building_offer_side(): void
    {
        $decoded = $this->decoder->decode('BuildBuilding,Mayorhouse,1|Coin,200000|1', TradeOfferDecoder::TRADE_BUFF_FOR_RES);

        $this->assertNotNull($decoded);
        $this->assertSame(MarketItemKind::Building, $decoded->offer->kind);
        $this->assertSame('BuildBuilding', $decoded->offer->baseName);
        $this->assertSame('Mayorhouse', $decoded->offer->subject);
        $this->assertSame(1, $decoded->offer->units);
    }

    public function test_defense_building_offer_side(): void
    {
        $decoded = $this->decoder->decode('BuildDefenseModeBuilding,Wall,1|Coin,200000|1', TradeOfferDecoder::TRADE_BUFF_FOR_RES);

        $this->assertNotNull($decoded);
        $this->assertSame(MarketItemKind::Building, $decoded->offer->kind);
        $this->assertSame('BuildDefenseModeBuilding', $decoded->offer->baseName);
        $this->assertSame('Wall', $decoded->offer->subject);
        $this->assertSame(1, $decoded->offer->units);
    }

    public function test_recurring_chance(): void
    {
        $decoded = $this->decoder->decode('HiredMilitary,,100,50|Coin,900|3', TradeOfferDecoder::TRADE_BUFF_FOR_RES);

        $this->assertNotNull($decoded);
        $this->assertSame(50, $decoded->offer->recurringChance);
    }

    public function test_two_field_buff(): void
    {
        $decoded = $this->decoder->decode('PremiumAccount,Days7|Coin,900|1', TradeOfferDecoder::TRADE_BUFF_FOR_RES);

        $this->assertNotNull($decoded);
        $this->assertSame(MarketItemKind::Buff, $decoded->offer->kind);
        $this->assertSame('PremiumAccount', $decoded->offer->baseName);
        $this->assertSame('Days7', $decoded->offer->subject);
        $this->assertNull($decoded->offer->rawAmount);
        $this->assertSame(1, $decoded->offer->units);
    }

    public function test_buff_for_buff(): void
    {
        $decoded = $this->decoder->decode('FillDeposit,Fish,100|FillDeposit,Meat,100|2', TradeOfferDecoder::TRADE_BUFF_FOR_BUFF);

        $this->assertNotNull($decoded);
        $this->assertSame(MarketItemKind::Buff, $decoded->offer->kind);
        $this->assertNotNull($decoded->costs);
        $this->assertSame(MarketItemKind::Buff, $decoded->costs->kind);
        $this->assertSame('Meat', $decoded->costs->subject);
    }

    #[DataProvider('invalidOfferDataProvider')]
    public function test_invalid_offers_return_null_without_throwing(string $offer, int $tradeType): void
    {
        $this->assertNull($this->decoder->decode($offer, $tradeType));
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function invalidOfferDataProvider(): array
    {
        return [
            'two segments' => ['Marble,100', 0],
            'four segments' => ['Marble,100|Coin,500|4|extra', 0],
            'empty string' => ['', 0],
            'non-numeric amount' => ['Marble,abc|Coin,500|1', 0],
            'invalid trade type 7' => ['Marble,100|Coin,500|1', 7],
            'negative trade type' => ['Marble,100|Coin,500|1', -1],
            'empty resource name' => [',100|Coin,500|1', 0],
            'zero resource amount' => ['Marble,0|Coin,500|1', 0],
            'negative resource amount' => ['Marble,-100|Coin,500|1', 0],
            'empty cost resource name' => ['Marble,100|,500|1', 0],
            'zero cost resource amount' => ['Marble,100|Coin,0|1', 0],
            'non-numeric cost amount' => ['Adventure,MadHenry,0|Coin,abc|1', 2],
        ];
    }
}
