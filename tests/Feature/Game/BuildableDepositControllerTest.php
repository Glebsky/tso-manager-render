<?php

declare(strict_types=1);

namespace Tests\Feature\Game;

use App\Models\Account;
use App\Models\User;
use App\Services\Game\Mines\BuildQueueSnapshot;
use App\Services\Game\Mines\Contracts\ZoneSnapshotProviderInterface;
use App\Services\Game\Mines\DepositSnapshot;
use App\Services\Game\Mines\ZoneSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class BuildableDepositControllerTest extends TestCase
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

    public function test_it_returns_buildable_deposits_list(): void
    {
        $this->actingAs($this->user);

        /** @var ZoneSnapshotProviderInterface&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $mockZones->shouldReceive('forAccount')
            ->once()
            ->andReturn(new ZoneSnapshot(
                depositsByGrid: [
                    6431 => new DepositSnapshot(grid: 6431, name: 'IronOre', amount: 1000, maxAmount: 1000),
                ],
                buildingsByGrid: [],
                buildQueue: new BuildQueueSnapshot(used: 1, total: 3),
            ));

        $this->app->instance(ZoneSnapshotProviderInterface::class, $mockZones);

        $response = $this->getJson('/api/game/buildable-deposits?account_id='.$this->account->id);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['grid', 'deposit_name', 'mine_name', 'amount', 'max_amount', 'allowed', 'reason'],
                ],
            ])
            ->assertJsonPath('data.0.grid', 6431)
            ->assertJsonPath('data.0.deposit_name', 'IronOre')
            ->assertJsonPath('data.0.mine_name', 'IronMine')
            ->assertJsonPath('data.0.allowed', true);
    }

    public function test_it_requires_authentication(): void
    {
        Auth::logout();
        $this->app['session']->flush();

        $response = $this->getJson('/api/game/buildable-deposits?account_id='.$this->account->id);
        $response->assertUnauthorized();
    }
}
