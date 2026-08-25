<?php

declare(strict_types=1);

namespace Tests\Feature\Game;

use App\Models\Account;
use App\Models\User;
use App\Services\Game\Mines\BuildingSnapshot;
use App\Services\Game\Mines\BuildQueueSnapshot;
use App\Services\Game\Mines\Contracts\ZoneSnapshotProviderInterface;
use App\Services\Game\Mines\ZoneSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class UpgradableMineControllerTest extends TestCase
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

    public function test_it_returns_upgradable_mines_list(): void
    {
        $this->actingAs($this->user);

        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->shouldReceive('forAccount')
            ->once()
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [],
                buildingsByGrid: [
                    6431 => new BuildingSnapshot(grid: 6431, name: 'IronMine', upgradeLevel: 2, isProductionActive: true, upgradeInProgress: false),
                ],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        $this->app->instance(ZoneSnapshotProviderInterface::class, $mockZones);

        $response = $this->getJson('/api/game/upgradable-mines?account_id='.$this->account->id);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['grid', 'building_name', 'deposit_name', 'level', 'max_level', 'is_active', 'upgrade_in_progress', 'allowed', 'reason'],
                ],
            ])
            ->assertJsonPath('data.0.grid', 6431)
            ->assertJsonPath('data.0.building_name', 'IronMine')
            ->assertJsonPath('data.0.deposit_name', 'IronOre')
            ->assertJsonPath('data.0.level', 2)
            ->assertJsonPath('data.0.allowed', true);
    }

    public function test_it_requires_authentication(): void
    {
        Auth::logout();
        $this->app['session']->flush();

        $response = $this->getJson('/api/game/upgradable-mines?account_id='.$this->account->id);
        $response->assertUnauthorized();
    }
}
