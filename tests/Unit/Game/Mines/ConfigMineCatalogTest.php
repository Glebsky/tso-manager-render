<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Mines;

use App\Services\Game\Mines\ConfigMineCatalog;
use Tests\TestCase;

final class ConfigMineCatalogTest extends TestCase
{
    private ConfigMineCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->catalog = new ConfigMineCatalog([
            'BronzeOre' => ['mine' => 'BronzeMine', 'number' => 36, 'max_level' => 7],
            'Coal' => ['mine' => 'CoalMine', 'number' => 37, 'max_level' => 7],
            'GoldOre' => ['mine' => 'GoldMine', 'number' => 46, 'max_level' => 7],
            'IronOre' => ['mine' => 'IronMine', 'number' => 50, 'max_level' => 7],
            'Salpeter' => ['mine' => 'SalpeterMine', 'number' => 63, 'max_level' => 7],
            'TitaniumOre' => ['mine' => 'TitaniumMine', 'number' => 69, 'max_level' => 7],
        ]);
    }

    public function test_it_resolves_every_mine_from_config_by_deposit_name(): void
    {
        $this->assertSame(36, $this->catalog->findByDeposit('BronzeOre')?->buildingNumber);
        $this->assertSame(37, $this->catalog->findByDeposit('Coal')?->buildingNumber);
        $this->assertSame(46, $this->catalog->findByDeposit('GoldOre')?->buildingNumber);
        $this->assertSame(50, $this->catalog->findByDeposit('IronOre')?->buildingNumber);
        $this->assertSame(63, $this->catalog->findByDeposit('Salpeter')?->buildingNumber);
        $this->assertSame(69, $this->catalog->findByDeposit('TitaniumOre')?->buildingNumber);
    }

    public function test_it_resolves_by_building_name(): void
    {
        $def = $this->catalog->findByBuilding('IronMine');
        $this->assertNotNull($def);
        $this->assertSame('IronOre', $def->depositName);
        $this->assertSame(50, $def->buildingNumber);
        $this->assertSame(7, $def->maxUpgradeLevel);
    }

    public function test_it_returns_null_for_unknown_deposit(): void
    {
        $this->assertNull($this->catalog->findByDeposit('Stone'));
        $this->assertNull($this->catalog->findByBuilding('Woodcutter'));
    }

    public function test_it_is_case_sensitive(): void
    {
        $this->assertNull($this->catalog->findByDeposit('ironore'));
        $this->assertNull($this->catalog->findByBuilding('Ironmine'));
        $this->assertNull($this->catalog->findByBuilding('Goldmine'));
    }

    public function test_it_exposes_exactly_six_definitions(): void
    {
        $this->assertCount(6, $this->catalog->all());
    }

    public function test_it_keeps_the_shipped_mine_numbers(): void
    {
        $shippedCatalog = new ConfigMineCatalog((array) config('game.buildings.mines', []));
        $this->assertCount(6, $shippedCatalog->all());

        $expected = [
            'BronzeOre' => ['mine' => 'BronzeMine', 'number' => 36, 'max_level' => 7],
            'Coal' => ['mine' => 'CoalMine', 'number' => 37, 'max_level' => 7],
            'GoldOre' => ['mine' => 'GoldMine', 'number' => 46, 'max_level' => 7],
            'IronOre' => ['mine' => 'IronMine', 'number' => 50, 'max_level' => 7],
            'Salpeter' => ['mine' => 'SalpeterMine', 'number' => 63, 'max_level' => 7],
            'TitaniumOre' => ['mine' => 'TitaniumMine', 'number' => 69, 'max_level' => 7],
        ];

        foreach ($expected as $deposit => $data) {
            $def = $shippedCatalog->findByDeposit($deposit);
            $this->assertNotNull($def, "Missing definition for deposit {$deposit}");
            $this->assertSame($data['mine'], $def->buildingName);
            $this->assertSame($data['number'], $def->buildingNumber);
            $this->assertSame($data['max_level'], $def->maxUpgradeLevel);
        }
    }
}
