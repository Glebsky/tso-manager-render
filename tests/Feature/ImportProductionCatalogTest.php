<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ImportProductionCatalogTest extends TestCase
{
    private string $tempIconsFile;

    private string $tempGlobalsFile;

    private string $tempOutputFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempIconsFile = sys_get_temp_dir().'/test_icons_'.uniqid('', true).'.xml';
        $this->tempGlobalsFile = sys_get_temp_dir().'/test_globals_'.uniqid('', true).'.xml';
        $this->tempOutputFile = sys_get_temp_dir().'/test_game_production_'.uniqid('', true).'.php';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempIconsFile)) {
            unlink($this->tempIconsFile);
        }
        if (file_exists($this->tempGlobalsFile)) {
            unlink($this->tempGlobalsFile);
        }
        if (file_exists($this->tempOutputFile)) {
            unlink($this->tempOutputFile);
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
    <Building name="NonProducerBuilding" productionType="-1" />
    <Building name="PlainBuildingWithoutProductionType" />
    <Building name="Bookbinder" productionType="2" />
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
    <TimedProductions>
        <TimedProductionList id="1" />
        <TimedProductionList id="2" type="hardcoded">
            <TimedProduction name="Manuscript" group="0" duration="1800" requiresUpgradeLevelMin="1" requiresUpgradeLevelMax="5">
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

        $exitCode = Artisan::call('tso:import-production-catalog', [
            '--icons' => $this->tempIconsFile,
            '--globals' => $this->tempGlobalsFile,
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
        $this->assertArrayNotHasKey('NonProducerBuilding', $config['producers']);
        $this->assertArrayNotHasKey('PlainBuildingWithoutProductionType', $config['producers']);

        // Recipes assertion
        // Type 1 gets recipes from dynamic buff pool
        $this->assertArrayHasKey(1, $config['recipes']);
        $type1Recipes = $config['recipes'][1];
        $this->assertCount(2, $type1Recipes);

        $costless = null;
        $withCost = null;
        foreach ($type1Recipes as $r) {
            if ($r['name'] === 'CostlessBuff') {
                $costless = $r;
            } elseif ($r['name'] === 'ProductivityBuffLvl1') {
                $withCost = $r;
            }
        }
        $this->assertNotNull($costless);
        $this->assertFalse($costless['costs_known']);
        $this->assertSame([], $costless['costs']);

        $this->assertNotNull($withCost);
        $this->assertTrue($withCost['costs_known']);
        $this->assertSame([['resource' => 'Fish', 'count' => 10]], $withCost['costs']);

        // Type 2 gets recipes from explicit timed list
        $this->assertArrayHasKey(2, $config['recipes']);
        $type2Recipes = $config['recipes'][2];
        $this->assertCount(1, $type2Recipes);
        $this->assertSame('Manuscript', $type2Recipes[0]['name']);
        $this->assertSame(1, $type2Recipes[0]['requires_upgrade_level_min']);
        $this->assertSame(5, $type2Recipes[0]['requires_upgrade_level_max']);
        // Costs sorted by resource name: Nib before SimplePaper
        $this->assertSame('Nib', $type2Recipes[0]['costs'][0]['resource']);
        $this->assertSame('SimplePaper', $type2Recipes[0]['costs'][1]['resource']);
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
