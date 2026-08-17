<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\Game\QuestTriggerBuildingProvider;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class ClickableBuildingEndpointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** @var mixed */
    private $amfMock;

    /** @var mixed */
    private $parserMock;

    /** @var mixed */
    private $questProviderMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Operator',
            'email' => 'op@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->amfMock = Mockery::mock(TsoAmfService::class);
        $this->parserMock = Mockery::mock(ZoneParserService::class);
        $this->questProviderMock = Mockery::mock(QuestTriggerBuildingProvider::class);

        $this->app->instance(TsoAmfService::class, $this->amfMock);
        $this->app->instance(ZoneParserService::class, $this->parserMock);
        $this->app->instance(QuestTriggerBuildingProvider::class, $this->questProviderMock);
    }

    private function createAccount(): Account
    {
        return Account::create([
            'username' => 'testuser',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'testuser',
            'dso_auth_user' => '10001',
            'zone_data' => [
                'buildings' => [
                    ['buildingGrid' => 7215, 'buildingName' => 'CollectibleHerbsBuilding'],
                    ['buildingGrid' => 7301, 'buildingName' => 'FlyingHouse'],
                    ['buildingGrid' => 7420, 'buildingName' => 'BalloonMarket'],
                    ['buildingGrid' => 1000, 'buildingName' => 'Woodcutter'],
                ],
            ],
        ]);
    }

    public function test_guest_is_rejected_with_401(): void
    {
        Auth::logout();
        $this->app['session']->flush();

        $response = $this->getJson('/api/game/clickable-buildings?account_id=1');
        $response->assertUnauthorized();
    }

    public function test_requires_valid_account_id(): void
    {
        $this->actingAs($this->user);

        $res1 = $this->getJson('/api/game/clickable-buildings');
        $res1->assertStatus(422);

        $res2 = $this->getJson('/api/game/clickable-buildings?account_id=999999');
        $res2->assertStatus(404);
    }

    public function test_returns_classified_buildings_and_availability(): void
    {
        $this->actingAs($this->user);
        $account = $this->createAccount();

        $this->questProviderMock->shouldReceive('forAccount')
            ->once()
            ->with(Mockery::on(fn (Account $a) => $a->id === $account->id))
            ->andReturn(['FlyingHouse']);

        $response = $this->getJson('/api/game/clickable-buildings?account_id='.$account->id);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['grid', 'building_name', 'kind', 'available'],
            ],
        ]);

        /** @var list<array{grid: int, building_name: string, kind: string, available: bool|null}> $data */
        $data = (array) $response->json('data');
        $this->assertCount(4, $data);

        $this->assertSame(7215, $data[0]['grid']);
        $this->assertSame('collectible', $data[0]['kind']);
        $this->assertTrue($data[0]['available']);

        $this->assertSame(7301, $data[1]['grid']);
        $this->assertSame('quest_gift', $data[1]['kind']);
        $this->assertTrue($data[1]['available']);

        $this->assertSame(7420, $data[2]['grid']);
        $this->assertSame('quest_gift', $data[2]['kind']);
        $this->assertFalse($data[2]['available']);

        $this->assertSame(1000, $data[3]['grid']);
        $this->assertSame('none', $data[3]['kind']);
        $this->assertNull($data[3]['available']);
    }

    public function test_quest_pool_failure_returns_200_with_available_null(): void
    {
        $this->actingAs($this->user);
        $account = $this->createAccount();

        // Quest pool fails (returns null)
        $this->questProviderMock->shouldReceive('forAccount')
            ->once()
            ->andReturnNull();

        $response = $this->getJson('/api/game/clickable-buildings?account_id='.$account->id);

        $response->assertOk();
        /** @var list<array{grid: int, building_name: string, kind: string, available: bool|null}> $data */
        $data = (array) $response->json('data');

        $flyingHouse = null;
        foreach ($data as $item) {
            if ($item['grid'] === 7301) {
                $flyingHouse = $item;
                break;
            }
        }

        $this->assertNotNull($flyingHouse);
        $this->assertSame('quest_gift', $flyingHouse['kind']);
        $this->assertNull($flyingHouse['available']);
    }

    public function test_endpoint_sends_no_mutating_game_command(): void
    {
        $this->actingAs($this->user);
        $account = $this->createAccount();

        $this->amfMock->shouldNotReceive('collectCollectible');
        $this->amfMock->shouldNotReceive('sendBuildingSelectedQuestTrigger');
        $this->amfMock->shouldNotReceive('executePickup');
        $this->amfMock->shouldNotReceive('sendSpecialist');

        $this->questProviderMock->shouldReceive('forAccount')->andReturn([]);

        $response = $this->getJson('/api/game/clickable-buildings?account_id='.$account->id);
        $response->assertOk();
    }
}
