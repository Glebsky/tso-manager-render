<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Production;

use App\Enums\ProductionRejectionReason;
use App\Services\Game\Production\ConfigProductionCatalog;
use App\Services\Game\Production\ProductionOrderPolicy;
use App\Support\Zone\ZoneSnapshot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookbinderTierCostTest extends TestCase
{
    /** @return array<string, array{mixed, int, string|null}> */
    public static function cases(): array
    {
        return [
            'first tier exact' => [0, 10, null],
            'before boundary' => [59, 16, null],
            'at boundary shortage' => [60, 16, 'insufficient_resources'],
            'real live counter67' => [67, 16, 'insufficient_resources'],
            'at boundary sufficient' => [60, 18, null],
            'highest tier' => [9999, 23, 'insufficient_resources'],
            'highest tier exact' => [9999, 24, null],
            'missing counter' => [null, 1000, 'resource_data_unavailable'],
            'negative counter' => [-1, 1000, 'resource_data_unavailable'],
            'noninteger counter' => ['67', 1000, 'resource_data_unavailable'],
        ];
    }

    #[DataProvider('cases')]
    public function test_current_price_uses_lifetime_counter_not_stock(mixed $produced, int $coins, ?string $reason): void
    {
        $policy = $this->policy();
        $zone = $this->zone($produced, $coins);
        $decision = $policy->decide(new ZoneSnapshot($zone), 44, 2, 'Manuscript', 1, 1);
        $this->assertSame($reason, $decision->reason?->value);
        $this->assertSame($reason === null, $decision->allowed);
    }

    public function test_missing_resource_data_is_not_sufficient_funds(): void
    {
        $zone = $this->zone(67, 1000);
        unset($zone['resources']);
        $this->assertSame(ProductionRejectionReason::ResourceDataUnavailable, $this->policy()->decide(new ZoneSnapshot($zone), 44, 2, 'Manuscript')->reason);
    }

    public function test_duplicate_counter_is_ambiguous(): void
    {
        $zone = $this->zone(67, 1000);
        $zone['resources'][] = ['name' => 'Manuscript', 'amount' => 0, 'producedAmount' => 0];
        $this->assertSame(ProductionRejectionReason::ResourceDataUnavailable, $this->policy()->decide(new ZoneSnapshot($zone), 44, 2, 'Manuscript')->reason);
    }

    private function policy(): ProductionOrderPolicy
    {
        return new ProductionOrderPolicy(new ConfigProductionCatalog([
            'producers' => ['Bookbinder' => 2],
            'metadata' => [2 => ['recipe_source' => 'skillpoints']],
            'recipes' => [2 => [[
                'name' => 'Manuscript', 'costs_known' => true, 'cost_is_lower_bound' => true,
                'costs' => [['resource' => 'Coin', 'count' => 10]],
                'cost_tiers' => [
                    ['threshold' => 0, 'costs' => [['resource' => 'Coin', 'count' => 10]]],
                    ['threshold' => 50, 'costs' => [['resource' => 'Coin', 'count' => 16]]],
                    ['threshold' => 60, 'costs' => [['resource' => 'Coin', 'count' => 18]]],
                    ['threshold' => 90, 'costs' => [['resource' => 'Coin', 'count' => 24]]],
                ],
            ]]],
        ]));
    }

    /** @return array<string, mixed> */
    private function zone(mixed $produced, int $coins): array
    {
        return [
            'buildings' => [['buildingGrid' => 44, 'buildingName_string' => 'Bookbinder']],
            'production_queues' => [],
            'resources' => [
                ['name_string' => 'Manuscript', 'amount' => 79, 'producedAmount' => $produced],
                ['name_string' => 'Coin', 'amount' => $coins],
            ],
        ];
    }
}
