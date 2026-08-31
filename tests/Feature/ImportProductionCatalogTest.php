<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ImportProductionCatalogTest extends TestCase
{
    private string $tempIconsFile;

    private string $tempGlobalsFile;

    private string $tempSkillpointsFile;

    private string $tempCollectionsFile;

    private string $tempUnitsFile;

    private string $tempOutputFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempIconsFile = sys_get_temp_dir().'/test_icons_'.uniqid('', true).'.xml';
        $this->tempGlobalsFile = sys_get_temp_dir().'/test_globals_'.uniqid('', true).'.xml';
        $this->tempSkillpointsFile = sys_get_temp_dir().'/test_skillpoints_'.uniqid('', true).'.xml';
        $this->tempCollectionsFile = sys_get_temp_dir().'/test_collections_'.uniqid('', true).'.xml';
        $this->tempUnitsFile = sys_get_temp_dir().'/test_units_'.uniqid('', true).'.xml';
        $this->tempOutputFile = sys_get_temp_dir().'/test_game_production_'.uniqid('', true).'.php';
    }

    protected function tearDown(): void
    {
        foreach ([
            $this->tempIconsFile,
            $this->tempGlobalsFile,
            $this->tempSkillpointsFile,
            $this->tempCollectionsFile,
            $this->tempUnitsFile,
            $this->tempOutputFile,
        ] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_it_imports_production_catalog_from_xml_fixtures(): void
    {
        $iconsXml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<Icons>
    <Building name="Barracks" productionType="0" />
    <Building name="ProvisionHouse" productionType="1" />
    <Building name="Bookbinder" productionType="2" />
    <Building name="MayorHouse" productionType="4" />
    <Building name="Barracks3" productionType="7" />
    <Building name="SpecialProvisionHouse" productionType="18" />
    <Building name="NonProducerBuilding" productionType="-1" />
    <Building name="PlainBuildingWithoutProductionType" />
</Icons>
XML;
        file_put_contents($this->tempIconsFile, $iconsXml);

        $globalsXml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<Globals>
    <Buffs>
        <Buff name="ProductivityBuffLvl1" produceable="true" group="0" productionTime="60" buffType="Timed">
            <Costs>
                <Cost name="Fish" count="10"/>
            </Costs>
        </Buff>
        <Buff name="CostlessBuff" produceable="true" group="0" productionTime="120" buffType="Instant" />
        <Buff name="UnproduceableBuff" produceable="false" group="0" productionTime="60" />
    </Buffs>
    <MilitaryUnits>
        <MilitaryUnit type="Recruit" produceable="true" productionTimeSeconds="180">
            <costs>
                <cost name="BronzeSword" count="10"/>
                <cost name="Population" count="1"/>
            </costs>
        </MilitaryUnit>
    </MilitaryUnits>
    <TimedProductions>
        <TimedProductionList id="1" />
        <TimedProductionList id="18" type="hardcoded">
            <TimedProduction name="SpecialFishFood" group="0" duration="1800" requiresUpgradeLevelMin="1" requiresUpgradeLevelMax="5">
                <Costs>
                    <Cost name="SimplePaper" count="250"/>
                    <Cost name="Nib" count="10"/>
                </Costs>
            </TimedProduction>
        </TimedProductionList>
    </TimedProductions>
</Globals>
XML;
        file_put_contents($this->tempGlobalsFile, $globalsXml);

        $skillpointsXml = <<<'XML'
<scienceSystem>
    <skillPoints>
        <skillPoint id="Manuscript" instantFinishCost="200">
            <productionLevel amountProduced="0" productionTime="1800">
                <cost name="SimplePaper" count="250" />
                <cost name="Water" count="250" />
            </productionLevel>
        </skillPoint>
    </skillPoints>
</scienceSystem>
XML;
        file_put_contents($this->tempSkillpointsFile, $skillpointsXml);

        $collectionsXml = <<<'XML'
<root>
    <collections>
        <collection name="RedNoseCollection" pLvl="16" productionTime="20" InstantBuildCosts="1">
            <resource name="CollectibleHerbs" amount="8" />
        </collection>
    </collections>
</root>
XML;
        file_put_contents($this->tempCollectionsFile, $collectionsXml);

        $unitsXml = <<<'XML'
<GameUnit>
    <Unit Type="ExpeditionCuirassier" Tier="3">
        <Properties>
            <Property Type="IsProducible" Value="1" />
            <Property Type="ProductionTime" Value="360" />
            <Property Type="InstantBuildCost" Value="10" />
        </Properties>
        <Costs>
            <Cost Type="Population" Amount="1" />
            <Cost Type="Beer" Amount="20" />
        </Costs>
    </Unit>
</GameUnit>
XML;
        file_put_contents($this->tempUnitsFile, $unitsXml);

        $exitCode = Artisan::call('tso:import-production-catalog', [
            '--icons' => $this->tempIconsFile,
            '--globals' => $this->tempGlobalsFile,
            '--skillpoints' => $this->tempSkillpointsFile,
            '--collections' => $this->tempCollectionsFile,
            '--units' => $this->tempUnitsFile,
            '--output' => $this->tempOutputFile,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->tempOutputFile);

        /** @var array{producers: array<string, int>, metadata: array<int, array<string, mixed>>, recipes: array<int, list<array<string, mixed>>>} $config */
        $config = require $this->tempOutputFile;

        // Producers assertion
        $this->assertArrayHasKey('Barracks', $config['producers']);
        $this->assertSame(0, $config['producers']['Barracks']);
        $this->assertArrayHasKey('ProvisionHouse', $config['producers']);
        $this->assertSame(1, $config['producers']['ProvisionHouse']);
        $this->assertArrayHasKey('Bookbinder', $config['producers']);
        $this->assertSame(2, $config['producers']['Bookbinder']);
        $this->assertArrayHasKey('MayorHouse', $config['producers']);
        $this->assertSame(4, $config['producers']['MayorHouse']);
        $this->assertArrayHasKey('Barracks3', $config['producers']);
        $this->assertSame(7, $config['producers']['Barracks3']);
        $this->assertArrayHasKey('SpecialProvisionHouse', $config['producers']);
        $this->assertSame(18, $config['producers']['SpecialProvisionHouse']);
        $this->assertArrayNotHasKey('NonProducerBuilding', $config['producers']);
        $this->assertArrayNotHasKey('PlainBuildingWithoutProductionType', $config['producers']);

        // Type 0: military units
        $this->assertArrayHasKey(0, $config['recipes']);
        $this->assertSame('Recruit', $config['recipes'][0][0]['name']);
        $this->assertTrue($config['recipes'][0][0]['stacks_supported']);

        // Type 1: buff pool
        $this->assertArrayHasKey(1, $config['recipes']);
        $type1Recipes = $config['recipes'][1];
        $this->assertCount(2, $type1Recipes);

        // Type 2: skillpoints
        $this->assertArrayHasKey(2, $config['recipes']);
        $this->assertSame('Manuscript', $config['recipes'][2][0]['name']);
        $this->assertFalse($config['recipes'][2][0]['stacks_supported']);
        $this->assertSame(1, $config['recipes'][2][0]['max_amount_per_order']);
        $this->assertTrue($config['recipes'][2][0]['cost_is_lower_bound']);

        // Type 4: collections
        $this->assertArrayHasKey(4, $config['recipes']);
        $this->assertSame('RedNoseCollection', $config['recipes'][4][0]['name']);
        $this->assertFalse($config['recipes'][4][0]['stacks_supported']);
        $this->assertTrue($config['recipes'][4][0]['unverified_protocol']);

        // Type 7: combat3 units
        $this->assertArrayHasKey(7, $config['recipes']);
        $this->assertSame('ExpeditionCuirassier', $config['recipes'][7][0]['name']);
        $this->assertTrue($config['recipes'][7][0]['stacks_supported']);

        // Type 18: explicit timed list
        $this->assertArrayHasKey(18, $config['recipes']);
        $type18Recipes = $config['recipes'][18];
        $this->assertCount(1, $type18Recipes);
        $this->assertSame('SpecialFishFood', $type18Recipes[0]['name']);
        $this->assertSame(1, $type18Recipes[0]['requires_upgrade_level_min']);
        $this->assertSame(5, $type18Recipes[0]['requires_upgrade_level_max']);
        // Costs sorted by resource name: Nib before SimplePaper
        $this->assertSame('Nib', $type18Recipes[0]['costs'][0]['resource']);
        $this->assertSame('SimplePaper', $type18Recipes[0]['costs'][1]['resource']);
    }

    public function test_import_is_deterministic_and_idempotent(): void
    {
        $iconsXml = '<Icons><Building name="ProvisionHouse" productionType="1" /><Building name="Barracks" productionType="0" /></Icons>';
        file_put_contents($this->tempIconsFile, $iconsXml);

        $globalsXml = '<Globals><Buffs><Buff name="B" produceable="true" group="0" productionTime="60"><Costs><Cost name="Wood" count="1"/></Costs></Buff><Buff name="A" produceable="true" group="0" productionTime="60"><Costs><Cost name="Fish" count="1"/></Costs></Buff></Buffs></Globals>';
        file_put_contents($this->tempGlobalsFile, $globalsXml);

        Artisan::call('tso:import-production-catalog', [
            '--icons' => $this->tempIconsFile,
            '--globals' => $this->tempGlobalsFile,
            '--output' => $this->tempOutputFile,
        ]);
        $output1 = file_get_contents($this->tempOutputFile);

        Artisan::call('tso:import-production-catalog', [
            '--icons' => $this->tempIconsFile,
            '--globals' => $this->tempGlobalsFile,
            '--output' => $this->tempOutputFile,
        ]);
        $output2 = file_get_contents($this->tempOutputFile);

        $this->assertSame($output1, $output2);
    }
}
