<?php

declare(strict_types=1);

namespace Tests\Feature\Game;

use App\Models\Account;
use App\Models\User;
use App\Services\Game\Production\ConfigProductionCatalog;
use App\Services\Game\Production\ProductionCatalogInterface;
use App\Services\Game\Production\ZoneSnapshotProviderInterface;
use App\Support\Zone\ZoneSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class BuffProducerControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->account = Account::create([
            'username' => 'test_user',
            'nickname' => 'TestPlayer',
            'password' => 'secret123',
            'server' => 'ru_1',
            'region' => 'ru',
        ]);
    }

    public function test_it_returns_buff_producers_list_with_queues_and_recipes(): void
    {
        $this->actingAs($this->user);

        $catalog = new ConfigProductionCatalog([
            'producers' => [
                'ProvisionHouse' => 1,
                'Bookbinder' => 2,
            ],
            'recipes' => [
                1 => [
                    [
                        'name' => 'ProductivityBuffLvl3',
                        'group' => 0,
                        'duration_seconds' => 1800,
                        'buff_type' => 'Timed',
                        'requires_upgrade_level_min' => 0,
                        'requires_upgrade_level_max' => 99,
                        'requires_event' => null,
                        'requires_quest' => null,
                        'costs' => [
                            ['resource' => 'Fish', 'count' => 120],
                        ],
                        'costs_known' => true,
                    ],
                ],
                2 => [
                    [
                        'name' => 'Tome',
                        'group' => 0,
                        'duration_seconds' => 3600,
                        'buff_type' => 'Timed',
                        'requires_upgrade_level_min' => 2,
                        'requires_upgrade_level_max' => 5,
                        'requires_event' => null,
                        'requires_quest' => null,
                        'costs' => [
                            ['resource' => 'IntermediatePaper', 'count' => 200],
                        ],
                        'costs_known' => true,
                    ],
                    [
                        'name' => 'Codex',
                        'group' => 0,
                        'duration_seconds' => 7200,
                        'buff_type' => 'Timed',
                        'requires_upgrade_level_min' => 4,
                        'requires_upgrade_level_max' => 5,
                        'requires_event' => null,
                        'requires_quest' => null,
                        'costs' => [
                            ['resource' => 'AdvancedPaper', 'count' => 200],
                        ],
                        'costs_known' => true,
                    ],
                ],
            ],
        ]);

        $this->app->instance(ProductionCatalogInterface::class, $catalog);

        $snapshot = new ZoneSnapshot([
            'buildings' => [
                [
                    'buildingGrid' => 100,
                    'buildingName_string' => 'ProvisionHouse',
                    'upgradeLevel' => 3,
                    'upgradeIsInProgress' => false,
                    'isProductionActive' => true,
                ],
                [
                    'buildingGrid' => 200,
                    'buildingName_string' => 'Bookbinder',
                    'upgradeLevel' => 2,
                    'upgradeIsInProgress' => false,
                    'isProductionActive' => true,
                ],
                [
                    'buildingGrid' => 300,
                    'buildingName_string' => 'Woodcutter',
                    'upgradeLevel' => 5,
                    'upgradeIsInProgress' => false,
                    'isProductionActive' => true,
                ],
            ],
            'production_queues' => [
                [
                    'production_type' => 2,
                    'orders' => [
                        [
                            'type_string' => 'Tome',
                            'amount' => 1,
                            'produced_items' => 1,
                            'collected_time' => 259200000.0,
                            'stacks' => 1,
                            'index' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->shouldReceive('forAccount')
            ->once()
            ->withArgs(fn (Account $acc) => $acc->id === $this->account->id)
            ->andReturn($snapshot);

        $this->app->instance(ZoneSnapshotProviderInterface::class, $mockZones);

        $response = $this->getJson('/api/game/buff-producers?account_id='.$this->account->id);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'grid',
                        'building_name',
                        'production_type',
                        'upgrade_level',
                        'upgrade_in_progress',
                        'production_active',
                        'queue' => ['used', 'orders'],
                        'recipes' => [
                            '*' => [
                                'name',
                                'group',
                                'duration_seconds',
                                'buff_type',
                                'requires_upgrade_level_min',
                                'requires_upgrade_level_max',
                                'is_locked',
                                'costs',
                                'costs_known',
                            ],
                        ],
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // ProvisionHouse (grid 100, empty queue, 1 recipe, unlocked)
        $this->assertSame(100, $data[0]['grid']);
        $this->assertSame('ProvisionHouse', $data[0]['building_name']);
        $this->assertSame(1, $data[0]['production_type']);
        $this->assertSame(0, $data[0]['queue']['used']);
        $this->assertSame([], $data[0]['queue']['orders']);
        $this->assertFalse($data[0]['recipes'][0]['is_locked']);

        // Bookbinder (grid 200, 1 order in queue, Tome unlocked at lvl 2, Codex locked at lvl 2)
        $this->assertSame(200, $data[1]['grid']);
        $this->assertSame('Bookbinder', $data[1]['building_name']);
        $this->assertSame(2, $data[1]['production_type']);
        $this->assertSame(1, $data[1]['queue']['used']);
        $this->assertSame('Tome', $data[1]['queue']['orders'][0]['type_string']);
        $this->assertFalse($data[1]['recipes'][0]['is_locked']); // Tome min=2, lvl=2 -> unlocked
        $this->assertTrue($data[1]['recipes'][1]['is_locked']);  // Codex min=4, lvl=2 -> locked
    }

    public function test_it_requires_authentication(): void
    {
        Auth::logout();
        $this->app['session']->flush();

        $response = $this->getJson('/api/game/buff-producers?account_id='.$this->account->id);
        $response->assertUnauthorized();
    }
}
