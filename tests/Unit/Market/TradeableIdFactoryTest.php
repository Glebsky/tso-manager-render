<?php

declare(strict_types=1);

namespace Tests\Unit\Market;

use App\Enums\MarketItemKind;
use App\Services\Market\Tradeables\TradeableIdFactory;
use App\Services\Market\Tradeables\TradeOfferDecoder;
use App\Services\Market\Tradeables\TradeSide;
use PHPUnit\Framework\TestCase;

class TradeableIdFactoryTest extends TestCase
{
    private TradeableIdFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new TradeableIdFactory;
    }

    public function test_from_side_resource(): void
    {
        $side = new TradeSide(
            kind: MarketItemKind::Resource,
            baseName: 'Marble',
            subject: null,
            rawAmount: 100,
            units: 100,
        );

        $id = $this->factory->fromSide($side);
        $this->assertSame('Marble', $id);

        $parsed = $this->factory->parse($id);
        $this->assertSame(MarketItemKind::Resource, $parsed['kind']);
        $this->assertSame('Marble', $parsed['base']);
        $this->assertNull($parsed['subject']);
    }

    public function test_from_side_adventure(): void
    {
        $side = new TradeSide(
            kind: MarketItemKind::Adventure,
            baseName: TradeOfferDecoder::ADVENTURE_BUFF,
            subject: 'MadHenry',
            rawAmount: 0,
            units: 1,
        );

        $id = $this->factory->fromSide($side);
        $this->assertSame('adventure:MadHenry', $id);

        $parsed = $this->factory->parse($id);
        $this->assertSame(MarketItemKind::Adventure, $parsed['kind']);
        $this->assertSame(TradeOfferDecoder::ADVENTURE_BUFF, $parsed['base']);
        $this->assertSame('MadHenry', $parsed['subject']);
    }

    public function test_from_side_building(): void
    {
        $side = new TradeSide(
            kind: MarketItemKind::Building,
            baseName: TradeOfferDecoder::BUILD_BUILDING_BUFF,
            subject: 'Mayorhouse',
            rawAmount: 1,
            units: 1,
        );

        $id = $this->factory->fromSide($side);
        $this->assertSame('building:Mayorhouse', $id);

        $parsed = $this->factory->parse($id);
        $this->assertSame(MarketItemKind::Building, $parsed['kind']);
        $this->assertSame(TradeOfferDecoder::BUILD_BUILDING_BUFF, $parsed['base']);
        $this->assertSame('Mayorhouse', $parsed['subject']);
    }

    public function test_from_side_buff_with_subject(): void
    {
        $side = new TradeSide(
            kind: MarketItemKind::Buff,
            baseName: 'FillDeposit',
            subject: 'Fish',
            rawAmount: 100,
            units: 1,
        );

        $id = $this->factory->fromSide($side);
        $this->assertSame('buff:FillDeposit:Fish', $id);

        $parsed = $this->factory->parse($id);
        $this->assertSame(MarketItemKind::Buff, $parsed['kind']);
        $this->assertSame('FillDeposit', $parsed['base']);
        $this->assertSame('Fish', $parsed['subject']);
    }

    public function test_from_side_buff_without_subject(): void
    {
        $side = new TradeSide(
            kind: MarketItemKind::Buff,
            baseName: 'ProductivityBuffLvl3',
            subject: null,
            rawAmount: 1,
            units: 1,
        );

        $id = $this->factory->fromSide($side);
        $this->assertSame('buff:ProductivityBuffLvl3', $id);

        $parsed = $this->factory->parse($id);
        $this->assertSame(MarketItemKind::Buff, $parsed['kind']);
        $this->assertSame('ProductivityBuffLvl3', $parsed['base']);
        $this->assertNull($parsed['subject']);
    }

    public function test_parse_legacy_or_unknown_prefix(): void
    {
        $parsed = $this->factory->parse('UnknownResource');
        $this->assertSame(MarketItemKind::Resource, $parsed['kind']);
        $this->assertSame('UnknownResource', $parsed['base']);
        $this->assertNull($parsed['subject']);

        $parsedInvalidPrefix = $this->factory->parse('foo:bar');
        $this->assertSame(MarketItemKind::Resource, $parsedInvalidPrefix['kind']);
        $this->assertSame('foo:bar', $parsedInvalidPrefix['base']);
        $this->assertNull($parsedInvalidPrefix['subject']);
    }
}
