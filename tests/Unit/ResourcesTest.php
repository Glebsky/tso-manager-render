<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Resources\AccountResource;
use App\Http\Resources\BotLogResource;
use App\Http\Resources\MarketOfferResource;
use App\Http\Resources\MarketSyncLogResource;
use App\Http\Resources\PopularItemResource;
use App\Http\Resources\PublicServerResource;
use App\Http\Resources\ScheduledTaskResource;
use App\Models\Account;
use App\Models\BotLog;
use App\Models\MarketOffer;
use App\Models\MarketServerConnection;
use App\Models\MarketSyncLog;
use App\Models\ScheduledTask;
use App\Services\Market\Contracts\ResourceNameResolver;
use App\Services\Market\MarketOfferQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use Tests\TestCase;

class ResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_resource_transformation(): void
    {
        $account = new Account;
        $account->forceFill([
            'id' => 1,
            'username' => 'testuser',
            'nickname' => 'TestNick',
            'status' => 'active',
            'zone_data' => ['avatarId' => 3, 'buildings' => [['id' => 101]]],
        ]);
        $account->setHidden(['password', 'zone_data']);

        $request = Request::create('/');
        $resource = (new AccountResource($account))->toArray($request);

        $this->assertEquals(1, $resource['id']);
        $this->assertEquals('testuser', $resource['username']);
        $this->assertEquals('TestNick', $resource['nickname']);
        $this->assertEquals('active', $resource['status']);
        $this->assertEquals(3, $resource['avatar_id']);
        $this->assertEquals(1, $resource['building_count']);
        $this->assertInstanceOf(MissingValue::class, $resource['zone_data']);

        $visibleResource = (new AccountResource($account))->withZoneData()->toArray($request);
        $this->assertIsArray($visibleResource['zone_data']);
    }

    public function test_scheduled_task_resource_transformation(): void
    {
        $task = new ScheduledTask;
        $task->forceFill([
            'id' => 10,
            'account_id' => 1,
            'name' => 'Test Task',
            'task_type' => 'apply_buff',
            'schedule_type' => 'daily',
            'run_at_time' => '12:00',
            'payload' => ['grid' => 123],
            'is_active' => true,
            'status' => 'pending',
        ]);

        $request = Request::create('/');
        $resource = (new ScheduledTaskResource($task))->toArray($request);

        $this->assertEquals(10, $resource['id']);
        $this->assertEquals(1, $resource['account_id']);
        $this->assertEquals('Test Task', $resource['name']);
        $this->assertEquals('apply_buff', $resource['task_type']);
        $this->assertEquals('daily', $resource['schedule_type']);
        $this->assertTrue($resource['is_active']);

    }

    public function test_bot_log_resource_transformation(): void
    {
        $log = new BotLog;
        $log->forceFill([
            'id' => 5,
            'account_id' => 1,
            'level' => 'info',
            'message' => 'Test log entry',
        ]);

        $request = Request::create('/');
        $resource = (new BotLogResource($log))->toArray($request);

        $this->assertEquals(5, $resource['id']);
        $this->assertEquals(1, $resource['account_id']);
        $this->assertEquals('info', $resource['level']);
        $this->assertEquals('Test log entry', $resource['message']);
    }

    public function test_popular_item_resource_transformation(): void
    {
        $data = [
            'item_id' => 'Oil',
            'item_name' => 'Oil Item',
            'offers_count' => 15,
            'sellers_count' => 4,
            'total_volume' => 1500.5,
        ];

        $request = Request::create('/');
        $resource = (new PopularItemResource($data))->toArray($request);

        $this->assertEquals('Oil', $resource['item_id']);
        $this->assertEquals('Oil Item', $resource['item_name']);
        $this->assertEquals(15, $resource['offers_count']);
        $this->assertEquals(4, $resource['sellers_count']);
        $this->assertEquals(1500.5, $resource['total_volume']);
    }

    public function test_public_server_resource_transformation(): void
    {
        $server = new MarketServerConnection;
        $server->forceFill([
            'id' => 1,
            'server_id' => 'ru_evelans',
            'locale' => 'ru',
            'display_name' => 'Evelance Market',
            'sync_status' => 'ok',
        ]);

        $request = Request::create('/');
        $resource = (new PublicServerResource($server))->toArray($request);

        $this->assertEquals(1, $resource['id']);
        $this->assertEquals('ru_evelans', $resource['server_id']);
        $this->assertEquals('Evelance', $resource['world_name']);
    }

    public function test_market_sync_log_resource_transformation(): void
    {
        $syncLog = new MarketSyncLog;
        $syncLog->forceFill([
            'id' => 1,
            'server_id' => 'ru_evelans',
            'action' => 'sync',
            'status' => 'success',
            'offers_fetched' => 120,
            'active_offers' => 100,
            'duration_ms' => 450,
            'message' => 'Synced OK',
            'created_at' => now(),
        ]);

        $request = Request::create('/');
        $resource = (new MarketSyncLogResource($syncLog))->toArray($request);

        $this->assertEquals('ru_evelans', $resource['server_id']);
        $this->assertEquals('sync', $resource['action']);
        $this->assertEquals('success', $resource['status']);
        $this->assertEquals('Synced OK', $resource['message']);
    }

    public function test_market_offer_resource_transformation(): void
    {
        $offer = new MarketOffer;
        $offer->forceFill([
            'id' => 1,
            'offer_id' => 999,
            'player_id' => 2,
            'sender_name' => 'Seller',
            'item_id' => 'Bread',
            'item_name' => 'Bread',
            'amount' => 10,
            'target_item_id' => 'Water',
            'target_item_name' => 'Water',
            'target_amount' => 5,
            'price' => 0.5,
            'volume' => 100,
            'lots_remaining' => 10,
            'created_at' => now(),
            'collected_at' => now(),
        ]);

        $names = $this->createMock(ResourceNameResolver::class);
        $names->method('resolve')->willReturnArgument(1);

        $offers = app(MarketOfferQueryService::class);

        $request = Request::create('/');
        $resource = (new MarketOfferResource($offer))->using($names, $offers)->toArray($request);

        $this->assertEquals(999, $resource['offer_id']);
        $this->assertEquals('Bread', $resource['item_id']);
        $this->assertEquals(0.5, $resource['price']);
    }
}
