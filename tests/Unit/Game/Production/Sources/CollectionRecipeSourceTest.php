<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Production\Sources;

use App\Services\Game\Production\Sources\CollectionRecipeSource;
use PHPUnit\Framework\TestCase;

final class CollectionRecipeSourceTest extends TestCase
{
    private string $xml = <<<'XML'
<root>
    <!-- Template example:
    <collection name="CountrySaying" minLevel="15" productionTime="120" InstantBuildCosts="200">
        <resource name="OldWood" amount="10"/>
    </collection>
    -->
    <collections>
        <collection name="RedNoseCollection" pLvl="16" outBuffName="AddResource_RedNoseCollection" productionTime="20" InstantBuildCosts="1" requiresEvent="RedNose_EventRunning">
            <resource name="CollectibleHerbs" amount="8" />
            <resource name="CollectibleScarecrow" amount="8" />
        </collection>
        <collection name="CountrySaying" pLvl="16" outBuffName="FillDeposit_Wheat_01" productionTime="300" InstantBuildCosts="15">
            <resource name="CollectibleHerbs" amount="10" />
        </collection>
    </collections>
</root>
XML;

    public function test_parses_uncommented_collections_with_unverified_protocol_and_no_stacks(): void
    {
        $source = new CollectionRecipeSource($this->xml);
        $recipes = $source->recipesFor(4);

        $this->assertCount(2, $recipes);

        $redNose = $recipes[0];
        $this->assertSame('RedNoseCollection', $redNose['name']);
        $this->assertSame(20, $redNose['duration_seconds']);
        $this->assertSame(1, $redNose['instant_finish_cost']);
        $this->assertSame(16, $redNose['requires_player_level_min']);
        $this->assertSame('AddResource_RedNoseCollection', $redNose['output_buff_name']);
        $this->assertSame('RedNose_EventRunning', $redNose['requires_event']);
        $this->assertTrue($redNose['unverified_protocol']);
        $this->assertFalse($redNose['stacks_supported']);
        $this->assertSame(25, $redNose['max_amount_per_order']);
        $this->assertSame(1, $redNose['max_stacks_per_order']);

        $countrySaying = $recipes[1];
        $this->assertSame('CountrySaying', $countrySaying['name']);
        $this->assertSame(300, $countrySaying['duration_seconds'], 'Must parse productionTime=300 from actual tag, not 120 from comment (P-31)');
        $this->assertSame(15, $countrySaying['instant_finish_cost']);
    }
}
