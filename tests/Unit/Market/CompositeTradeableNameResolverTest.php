<?php

declare(strict_types=1);

namespace Tests\Unit\Market;

use App\Enums\MarketItemKind;
use App\Services\Lang\GameTranslationResolver;
use App\Services\Market\GameResourceNameResolver;
use App\Services\Market\Tradeables\CompositeTradeableNameResolver;
use App\Services\Market\Tradeables\TradeableIdFactory;
use App\Services\Market\Tradeables\TradeSide;
use Tests\TestCase;

class CompositeTradeableNameResolverTest extends TestCase
{
    private CompositeTradeableNameResolver $compositeResolver;

    private GameResourceNameResolver $legacyResolver;

    protected function setUp(): void
    {
        parent::setUp();

        $translationResolver = $this->app->make(GameTranslationResolver::class);
        $idFactory = new TradeableIdFactory;

        $this->compositeResolver = new CompositeTradeableNameResolver($translationResolver, $idFactory);
        $this->legacyResolver = new GameResourceNameResolver($translationResolver);

        app()->setLocale('ru');
    }

    public function test_adventure_name_resolved_from_adn_section(): void
    {
        $this->assertSame('Дикая Мери', $this->compositeResolver->resolve('adventure:MadHenry', ''));
        $this->assertSame('Болотная ведьма', $this->compositeResolver->resolve('adventure:WitchOfTheSwamp', ''));
    }

    public function test_resource_name_matches_legacy_resolver(): void
    {
        $resources = ['Marble', 'Coin', 'Stone', 'Wood', 'RealEstateSaleContract'];

        foreach ($resources as $res) {
            $composite = $this->compositeResolver->resolve($res, '');
            $legacy = $this->legacyResolver->resolve($res, '');

            $this->assertSame($legacy, $composite, "Mismatch for resource {$res}");
        }
    }

    public function test_fill_deposit_without_subject_uses_fill_deposit_any(): void
    {
        $resolved = $this->compositeResolver->resolve('buff:FillDeposit', '');

        $this->assertNotEmpty($resolved);
        $this->assertStringNotContainsString('FillDeposit', $resolved);
    }

    public function test_building_name_resolved_from_bui_section(): void
    {
        $resolved = $this->compositeResolver->resolve('building:Mayorhouse', '');

        $this->assertNotEmpty($resolved);
        $this->assertNotSame('building:Mayorhouse', $resolved);
    }

    public function test_buff_with_subject_and_amount_in_trade_side(): void
    {
        $side = new TradeSide(
            kind: MarketItemKind::Buff,
            baseName: 'FillDeposit',
            subject: 'Fish',
            rawAmount: 100,
            units: 1,
        );

        $resolved = $this->compositeResolver->resolveTradeSide($side);
        $this->assertNotEmpty($resolved);
        $this->assertStringContainsString('100', $resolved);
    }

    public function test_productivity_buff_resolved_from_res_section(): void
    {
        $resolved = $this->compositeResolver->resolve('buff:ProductivityBuffLvl3', '');
        $this->assertNotEmpty($resolved);
        $this->assertNotSame('buff:ProductivityBuffLvl3', $resolved);
    }

    public function test_change_color_scheme_resolved(): void
    {
        $resolved = $this->compositeResolver->resolve('buff:ChangeColorScheme:Blue', '');
        $this->assertNotEmpty($resolved);
        $this->assertNotSame('buff:ChangeColorScheme:Blue', $resolved);
    }

    public function test_unknown_id_does_not_throw_and_does_not_return_composite_id(): void
    {
        $this->assertSame('CustomFallback', $this->compositeResolver->resolve('adventure:NonExistent123', 'CustomFallback'));
        $this->assertSame('NonExistent123', $this->compositeResolver->resolve('adventure:NonExistent123', ''));
        $this->assertSame('NonExistent123', $this->compositeResolver->resolve('buff:NonExistent123', ''));
    }

    public function test_null_or_empty_id_returns_fallback_or_empty(): void
    {
        $this->assertSame('', $this->compositeResolver->resolve(null, null));
        $this->assertSame('', $this->compositeResolver->resolve('', ''));
        $this->assertSame('FallbackName', $this->compositeResolver->resolve(null, 'FallbackName'));
        $this->assertSame('FallbackName', $this->compositeResolver->resolve('', 'FallbackName'));
    }
}
