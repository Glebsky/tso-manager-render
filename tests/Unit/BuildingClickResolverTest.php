<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\BuildingClickMode;
use App\Enums\CollectibleKind;
use App\Exceptions\BuildingNotClickableException;
use App\Services\Game\BuildingClickResolver;
use App\Services\Game\ClickableBuildingRegistry;
use Tests\TestCase;

class BuildingClickResolverTest extends TestCase
{
    /**
     * @param  list<array<string, mixed>>  $patterns
     */
    private function resolver(array $patterns = []): BuildingClickResolver
    {
        if ($patterns === []) {
            $patterns = [
                ['pattern' => '/^Collectible.+Building$/i', 'kind' => 0],
                ['pattern' => '/^StarfallStarDust.*$/i', 'kind' => 1],
            ];
        }

        return new BuildingClickResolver(new ClickableBuildingRegistry($patterns));
    }

    public function test_auto_mode_with_allowlist_match_resolves_to_collectible(): void
    {
        $resolver = $this->resolver();

        $decision = $resolver->resolve(BuildingClickMode::Auto, 100, 'CollectibleHerbsBuilding');

        $this->assertSame(BuildingClickMode::Collectible, $decision->mode);
        $this->assertSame(100, $decision->grid);
        $this->assertSame('CollectibleHerbsBuilding', $decision->buildingName);
        $this->assertSame(CollectibleKind::Normal, $decision->kind);
        $this->assertSame('allowlist_match', $decision->reason);
    }

    public function test_auto_mode_without_allowlist_match_falls_back_to_quest_trigger(): void
    {
        $resolver = $this->resolver();

        $decision = $resolver->resolve(BuildingClickMode::Auto, 200, 'FlyingHouse');

        $this->assertSame(BuildingClickMode::QuestTrigger, $decision->mode);
        $this->assertSame(200, $decision->grid);
        $this->assertSame('FlyingHouse', $decision->buildingName);
        $this->assertNull($decision->kind);
        $this->assertSame('auto_fallback_safe', $decision->reason);
    }

    public function test_explicit_collectible_mode_with_allowlist_match_resolves_to_collectible(): void
    {
        $resolver = $this->resolver();

        $decision = $resolver->resolve(BuildingClickMode::Collectible, 300, 'StarfallStarDust_1');

        $this->assertSame(BuildingClickMode::Collectible, $decision->mode);
        $this->assertSame(300, $decision->grid);
        $this->assertSame('StarfallStarDust_1', $decision->buildingName);
        $this->assertSame(CollectibleKind::Event, $decision->kind);
        $this->assertSame('explicit_allowlist_match', $decision->reason);
    }

    public function test_explicit_collectible_mode_on_production_building_throws_exception(): void
    {
        $resolver = $this->resolver();

        $this->expectException(BuildingNotClickableException::class);

        $resolver->resolve(BuildingClickMode::Collectible, 400, 'Woodcutter');
    }

    public function test_explicit_quest_trigger_mode_always_resolves_to_quest_trigger(): void
    {
        $resolver = $this->resolver();

        $decision = $resolver->resolve(BuildingClickMode::QuestTrigger, 500, 'CollectibleHerbsBuilding');

        $this->assertSame(BuildingClickMode::QuestTrigger, $decision->mode);
        $this->assertSame(500, $decision->grid);
        $this->assertSame('CollectibleHerbsBuilding', $decision->buildingName);
        $this->assertNull($decision->kind);
        $this->assertSame('explicit_quest_trigger', $decision->reason);
    }

    public function test_edge_case_empty_building_name_in_auto_resolves_to_quest_trigger(): void
    {
        $resolver = $this->resolver();

        $decision = $resolver->resolve(BuildingClickMode::Auto, 600, '');

        $this->assertSame(BuildingClickMode::QuestTrigger, $decision->mode);
        $this->assertSame('auto_fallback_safe', $decision->reason);
    }

    public function test_edge_case_empty_config_in_auto_resolves_to_quest_trigger(): void
    {
        $resolver = new BuildingClickResolver(new ClickableBuildingRegistry([]));

        $decision = $resolver->resolve(BuildingClickMode::Auto, 700, 'CollectibleHerbsBuilding');

        $this->assertSame(BuildingClickMode::QuestTrigger, $decision->mode);
        $this->assertSame('auto_fallback_safe', $decision->reason);
    }
}
