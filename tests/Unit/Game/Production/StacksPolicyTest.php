<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Production;

use App\Services\Game\Production\ConfigProductionCatalog;
use Tests\TestCase;

final class StacksPolicyTest extends TestCase
{
    private ConfigProductionCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $config = config('game_production');
        $this->assertIsArray($config);
        $this->catalog = new ConfigProductionCatalog($config);
    }

    public function test_type_2_bookbinder_disallows_stacks_and_limits_amount_to_one(): void
    {
        $recipes = $this->catalog->recipesFor(2);
        $this->assertNotEmpty($recipes);

        foreach ($recipes as $r) {
            $this->assertFalse($r->stacksSupported, "Recipe {$r->name} in type 2 must not support stacks");
            $this->assertSame(1, $r->maxStacksPerOrder);
            $this->assertSame(1, $r->maxAmountPerOrder);
        }
    }

    public function test_type_4_mayorhouse_disallows_stacks_with_normal_amount(): void
    {
        $recipes = $this->catalog->recipesFor(4);
        $this->assertNotEmpty($recipes);

        foreach ($recipes as $r) {
            $this->assertFalse($r->stacksSupported, "Recipe {$r->name} in type 4 must not support stacks");
            $this->assertSame(1, $r->maxStacksPerOrder);
            $this->assertSame(25, $r->maxAmountPerOrder);
        }
    }

    public function test_type_7_barracks3_allows_stacks(): void
    {
        $recipes = $this->catalog->recipesFor(7);
        $this->assertNotEmpty($recipes);

        foreach ($recipes as $r) {
            $this->assertTrue($r->stacksSupported, "Recipe {$r->name} in type 7 must support stacks");
            $this->assertSame(200, $r->maxStacksPerOrder);
            $this->assertSame(25, $r->maxAmountPerOrder);
        }
    }

    public function test_type_18_provisionhouse2_allows_stacks(): void
    {
        $recipes = $this->catalog->recipesFor(18);
        $this->assertNotEmpty($recipes);

        foreach ($recipes as $r) {
            $this->assertTrue($r->stacksSupported, "Recipe {$r->name} in type 18 must support stacks");
            $this->assertSame(200, $r->maxStacksPerOrder);
            $this->assertSame(25, $r->maxAmountPerOrder);
        }
    }

    public function test_type_27_christmas_bakery_disallows_stacks(): void
    {
        $recipes = $this->catalog->recipesFor(27);
        $this->assertNotEmpty($recipes);

        foreach ($recipes as $r) {
            $this->assertFalse($r->stacksSupported, "Recipe {$r->name} in type 27 must not support stacks");
            $this->assertSame(1, $r->maxStacksPerOrder);
            $this->assertSame(25, $r->maxAmountPerOrder);
        }
    }

    public function test_culturebuilding_lists_disallow_stacks(): void
    {
        // Check all types where list_type === 'culturebuilding'
        $config = config('game_production');
        $metadata = $config['metadata'] ?? [];

        $cultureTypes = [];
        foreach ($metadata as $type => $meta) {
            if (($meta['list_type'] ?? '') === 'culturebuilding') {
                $cultureTypes[] = (int) $type;
            }
        }

        $this->assertGreaterThanOrEqual(20, count($cultureTypes), 'Must find all culturebuilding types');

        foreach ($cultureTypes as $type) {
            $recipes = $this->catalog->recipesFor($type);
            foreach ($recipes as $r) {
                $this->assertFalse(
                    $r->stacksSupported,
                    "Culture building recipe {$r->name} in type {$type} must not support stacks"
                );
                $this->assertSame(1, $r->maxStacksPerOrder);
            }
        }
    }
}
