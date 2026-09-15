<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\BuildingNotBuffableException;
use App\Services\Tasks\Execution\BuildingBuffEligibilityValidator;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

final class BuildingBuffEligibilityValidatorTest extends TestCase
{
    private BuildingBuffEligibilityValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new BuildingBuffEligibilityValidator;
    }

    public function test_throws_when_building_not_found(): void
    {
        $buildings = [
            ['buildingGrid' => 100, 'buildingName_string' => 'Farm'],
        ];

        $this->expectException(BuildingNotBuffableException::class);
        $this->expectExceptionMessage('Building at grid 200 was not found.');

        $this->validator->validate($buildings, 200);
    }

    public function test_throws_when_building_is_depleted_mine(): void
    {
        $buildings = [
            [
                'buildingGrid' => 100,
                'buildingName_string' => 'MineDepletedDepositIron',
                'buildingMode' => 28,
                'isProductionActive' => false,
            ],
        ];

        $this->expectException(BuildingNotBuffableException::class);
        $this->expectExceptionMessage('Mine MineDepletedDepositIron (grid 100) is depleted.');

        $this->validator->validate($buildings, 100);
    }

    public function test_throws_when_building_is_upgrading(): void
    {
        $buildings = [
            [
                'buildingGrid' => 100,
                'buildingName_string' => 'IronSmelter',
                'upgradeIsInProgress' => true,
                'isProductionActive' => true,
                'buildingMode' => 0,
            ],
        ];

        $this->expectException(BuildingNotBuffableException::class);
        $this->expectExceptionMessage('Building IronSmelter (grid 100) is currently upgrading.');

        $this->validator->validate($buildings, 100);
    }

    public function test_throws_when_building_production_is_stopped_mode_28(): void
    {
        $buildings = [
            [
                'buildingGrid' => 100,
                'buildingName_string' => 'Brewery',
                'buildingMode' => 28,
                'isProductionActive' => true,
            ],
        ];

        $this->expectException(BuildingNotBuffableException::class);
        $this->expectExceptionMessage('Production for building Brewery (grid 100) is stopped.');

        $this->validator->validate($buildings, 100);
    }

    public function test_throws_when_building_production_is_inactive(): void
    {
        $buildings = [
            [
                'buildingGrid' => 100,
                'buildingName_string' => 'Brewery',
                'buildingMode' => 0,
                'isProductionActive' => false,
            ],
        ];

        $this->expectException(BuildingNotBuffableException::class);
        $this->expectExceptionMessage('Production for building Brewery (grid 100) is stopped.');

        $this->validator->validate($buildings, 100);
    }

    public function test_throws_when_building_is_already_buffed(): void
    {
        $buildings = [
            [
                'buildingGrid' => 100,
                'buildingName_string' => 'MarbleMason',
                'buildingMode' => 0,
                'isProductionActive' => true,
                'buffs' => [
                    [
                        'buffName_string' => 'ProductivityBuffLvl3',
                        'remaining' => 1000,
                    ],
                ],
            ],
        ];

        $this->expectException(BuildingNotBuffableException::class);
        $this->expectExceptionMessage('Building MarbleMason (grid 100) is already buffed.');

        $this->validator->validate($buildings, 100);
    }

    public function test_translates_message_in_russian_locale(): void
    {
        App::setLocale('ru');

        $buildings = [
            [
                'buildingGrid' => 100,
                'buildingName_string' => 'MarbleMason',
                'buffs' => [['buffName_string' => 'ProductivityBuffLvl3']],
            ],
        ];

        $this->expectException(BuildingNotBuffableException::class);
        $this->expectExceptionMessage('Здание MarbleMason (сетка 100) уже под баффом.');

        $this->validator->validate($buildings, 100);
    }

    public function test_accepts_valid_eligible_building(): void
    {
        $buildings = [
            [
                'buildingGrid' => 100,
                'buildingName_string' => 'IronSmelter',
                'buildingMode' => 0,
                'isProductionActive' => true,
                'buffs' => [],
            ],
        ];

        $result = $this->validator->validate(['buildings' => $buildings], 100);

        $this->assertSame('IronSmelter', $result['buildingName_string']);
        $this->assertSame(100, $result['buildingGrid']);
    }
}
