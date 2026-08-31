<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Game\Production\ConfigProductionCatalog;
use App\Services\Game\Production\GameXmlLocator;
use Tests\TestCase;
use XMLReader;

final class CatalogCoverageTest extends TestCase
{
    public function test_catalog_covers_all_producer_types_from_icons_xml(): void
    {
        $locator = new GameXmlLocator;
        $baseDir = base_path('docs/references/xml');

        if (! is_dir($baseDir)) {
            $this->markTestSkipped("References XML dir not found at {$baseDir}");
        }

        $files = $locator->locateAll($baseDir);
        $iconsPath = $files[GameXmlLocator::ROLE_ICONS];

        // 1. Extract all productionTypes from ICONS XML
        $producerTypesFromIcons = [];
        $reader = new XMLReader;
        $reader->open($iconsPath);

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'Building') {
                $ptAttr = $reader->getAttribute('productionType');
                if ($ptAttr !== null && (int) $ptAttr >= 0) {
                    $producerTypesFromIcons[(int) $ptAttr] = true;
                }
            }
        }
        $reader->close();

        $this->assertCount(69, $producerTypesFromIcons, 'ICONS XML should contain exactly 69 distinct producer types');

        // 2. Load compiled config/game_production.php
        $config = config('game_production');
        $this->assertIsArray($config, 'config/game_production.php must be loaded');

        $catalog = new ConfigProductionCatalog($config);

        // 3. Verify INV-6 for each productionType from ICONS
        foreach (array_keys($producerTypesFromIcons) as $productionType) {
            $source = $catalog->recipeSourceFor($productionType);
            $this->assertNotNull(
                $source,
                "Production type {$productionType} from ICONS has no recipe_source metadata"
            );

            $recipes = $catalog->recipesFor($productionType);

            if (str_starts_with($source, 'unsupported:')) {
                $this->assertCount(
                    0,
                    $recipes,
                    "Unsupported production type {$productionType} should have 0 recipes"
                );
            } else {
                $this->assertNotEmpty(
                    $recipes,
                    "Production type {$productionType} (source '{$source}') returned 0 recipes (INV-6 violation)"
                );
            }
        }
    }

    public function test_exact_recipe_counts_for_specialized_sources(): void
    {
        $config = config('game_production');
        $this->assertIsArray($config);

        $catalog = new ConfigProductionCatalog($config);

        // Type 0 (Barracks): 9 regular units
        $type0 = $catalog->recipesFor(0);
        $this->assertCount(9, $type0);
        $type0Names = array_map(fn ($r) => $r->name, $type0);
        $this->assertContains('EliteSoldier', $type0Names, 'EliteSoldier must be in type 0 (P-38)');
        $this->assertContains('Recruit', $type0Names);

        // Type 1 (ProvisionHouse): full dynamic buff pool
        $type1 = $catalog->recipesFor(1);
        $this->assertCount(298, $type1);

        // Type 2 (Bookbinder): 3 skill points
        $type2 = $catalog->recipesFor(2);
        $this->assertCount(3, $type2);
        $type2Names = array_map(fn ($r) => $r->name, $type2);
        $this->assertSame(['Codex', 'Manuscript', 'Tome'], $type2Names);

        // Type 4 (Mayorhouse): 16 collections
        $type4 = $catalog->recipesFor(4);
        $this->assertCount(16, $type4);
        $type4Names = array_map(fn ($r) => $r->name, $type4);
        $this->assertContains('CountrySaying', $type4Names);
        $countrySaying = $catalog->findRecipe(4, 'CountrySaying');
        $this->assertNotNull($countrySaying);
        $this->assertSame(300, $countrySaying->durationSeconds, 'CountrySaying must have duration 300, not 120 from comment (P-31)');

        // Type 6 (ExpeditionWeaponSmith): 6 buffs from group 5
        $type6 = $catalog->recipesFor(6);
        $this->assertCount(6, $type6);

        // Type 7 (Barracks3): 7 combat3 units (v1.1)
        $type7 = $catalog->recipesFor(7);
        $this->assertCount(7, $type7);
        $type7Names = array_map(fn ($r) => $r->name, $type7);
        $this->assertContains('ExpeditionCuirassier', $type7Names);
        $this->assertContains('ExpeditionTank', $type7Names);
        $this->assertContains('ExpeditionPikeman', $type7Names);

        // Type 8 (EliteBarracks): 7 elite units
        $type8 = $catalog->recipesFor(8);
        $this->assertCount(7, $type8);
        $type8Names = array_map(fn ($r) => $r->name, $type8);
        $this->assertContains('Swordsman', $type8Names);
        $this->assertContains('Besieger', $type8Names);

        // Type 11 (ExpeditionWeaponSmith2): 6 buffs from group 11
        $type11 = $catalog->recipesFor(11);
        $this->assertCount(6, $type11);

        // Type 6 and Type 11 sets must be disjoint
        $type6Names = array_map(fn ($r) => $r->name, $type6);
        $type11Names = array_map(fn ($r) => $r->name, $type11);
        $this->assertEmpty(array_intersect($type6Names, $type11Names));
    }

    public function test_specialized_sources_have_positive_durations(): void
    {
        $config = config('game_production');
        $this->assertIsArray($config);

        $catalog = new ConfigProductionCatalog($config);

        // Types 0, 1, 2, 4, 6, 7, 8, 11 must all have positive duration
        foreach ([0, 1, 2, 4, 6, 7, 8, 11] as $type) {
            foreach ($catalog->recipesFor($type) as $recipe) {
                $this->assertGreaterThan(
                    0,
                    $recipe->durationSeconds,
                    "Recipe '{$recipe->name}' in productionType {$type} has 0 duration (P-29)"
                );
            }
        }
    }
}
