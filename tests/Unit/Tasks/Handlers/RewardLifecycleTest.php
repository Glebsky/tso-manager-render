<?php

declare(strict_types=1);

namespace Tests\Unit\Tasks\Handlers;

use App\Exceptions\GameServerErrorException;
use App\Models\Account;
use App\Services\Game\Commands\CommandResponseDecoder;
use App\Services\Game\Production\ConfigProductionCatalog;
use App\Services\Game\Production\ProductionCommandGatewayInterface;
use App\Services\Game\Production\ProductionOrderPolicy;
use App\Services\Game\Production\ZoneSnapshotProviderInterface;
use App\Services\Game\Quests\DailyLoginQuestSelector;
use App\Services\Tasks\Handlers\CollectDailyLoginRewardHandler;
use App\Services\Tasks\Handlers\ProduceBuffHandler;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;
use App\Support\Zone\ZoneSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RewardLifecycleTest extends TestCase
{
    private function account(): Account
    {
        $account = new Account;
        $account->id = 971;
        $account->dso_auth_user = '1234';

        return $account;
    }

    /** @return array<string,mixed> */
    private function quest(int $mode = 5): array
    {
        return ['mQuestMode' => $mode, 'mUniqueID' => ['uniqueID1' => 0, 'uniqueID2' => 123],
            'mQuestDefinition' => ['showRewardWindow' => true, 'questTriggers_vector' => [['type' => 13]]]];
    }

    public function test_daily_selector_distinguishes_missing_empty_and_unrelated_quests(): void
    {
        $selector = new DailyLoginQuestSelector;
        $this->assertNull($selector->quests([]));
        $this->assertSame([], $selector->quests(['questPool' => ['mQuestVO_vector' => []]]));
        $this->assertTrue($selector->isClaimable($this->quest()));
        $this->assertFalse($selector->isClaimable($this->quest(3)));
        $quest = $this->quest();
        $quest['mQuestDefinition']['questTriggers_vector'][0]['type'] = 12;
        $this->assertFalse($selector->isClaimable($quest));
        $quest = $this->quest();
        $quest['mUniqueID']['uniqueID2'] = 0;
        $this->assertFalse($selector->isClaimable($quest));
    }

    public function test_daily_accepts_once_and_confirms_transition(): void
    {
        $this->dailyScenario(['server_response_confirmed' => true, 'errorCode' => 0], [], 'tasks.daily_login.collected');
    }

    public function test_daily_does_not_report_success_if_quest_stays_pending(): void
    {
        $this->dailyScenario(['server_response_confirmed' => true, 'errorCode' => 0], [$this->quest()], 'tasks.daily_login.unknown_outcome');
    }

    public function test_daily_unknown_mode_on_remaining_uid_is_not_collection_proof(): void
    {
        $this->dailyScenario(['server_response_confirmed' => true, 'errorCode' => 0], [$this->quest(777)], 'tasks.daily_login.unknown_outcome');
    }

    public function test_daily_missing_ack_does_not_trigger_confirmation_or_repeat(): void
    {
        $this->dailyScenario(['errorCode' => 0], null, 'tasks.daily_login.unknown_outcome');
    }

    /** @param array<string,mixed> $ack
     * @param  list<array<string,mixed>>|null  $latest
     */
    private function dailyScenario(array $ack, ?array $latest, string $expected): void
    {
        $account = $this->account();
        $amf = Mockery::mock(TsoAmfService::class);
        $parser = Mockery::mock(ZoneParserService::class);
        $amf->shouldReceive('getLatestQuestList')->with($account)->once()->andReturn('before');
        $parser->shouldReceive('parse')->with('before')->once()->andReturn(['server_response_confirmed' => true, 'errorCode' => 0, 'questPool' => ['mQuestVO_vector' => [$this->quest()]]]);
        $amf->shouldReceive('sendDailyLoginRewardOk')->with($account, 0, 123)->once()->andReturn('ack');
        $parser->shouldReceive('parse')->with('ack')->once()->andReturn($ack);
        if ($latest !== null) {
            $amf->shouldReceive('getLatestQuestList')->with($account)->once()->andReturn('after');
            $parser->shouldReceive('parse')->with('after')->once()->andReturn(['server_response_confirmed' => true, 'errorCode' => 0, 'questPool' => ['mQuestVO_vector' => $latest]]);
        }
        $handler = new CollectDailyLoginRewardHandler($amf, $parser, new DailyLoginQuestSelector, new CommandResponseDecoder($parser));
        $this->assertSame(__($expected), $handler->handle($account, ['scope' => 'self']));
    }

    /** @return array<string,mixed> */
    private function zone(bool $ready = true): array
    {
        return ['server_response_confirmed' => true, 'errorCode' => 0,
            'buildings' => [['buildingGrid' => 44, 'buildingName_string' => 'Bookbinder', 'upgradeLevel' => 3]],
            'production_queues' => [['production_type' => 2, 'orders' => [['type_string' => 'Tome', 'amount' => 1, 'produced_items' => $ready ? 1 : 0, 'collected_time' => 999999999.0, 'stacks' => 1, 'index' => 1]]]]];
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        return ['grid' => 44, 'production_type' => 2, 'recipe_name' => 'Tome', 'amount' => 1, 'stacks' => 1];
    }

    /** @return array<string,mixed> */
    private function catalog(): array
    {
        return ['producers' => ['Bookbinder' => 2], 'metadata' => [2 => ['recipe_source' => 'skillpoints']], 'recipes' => [2 => [['name' => 'Tome', 'max_amount_per_order' => 1, 'max_stacks_per_order' => 1, 'stacks_supported' => false, 'costs' => [], 'costs_known' => true]]]];
    }

    public function test_bookbinder_claims_refreshes_and_only_then_starts(): void
    {
        $account = $this->account();
        $zones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $gateway = Mockery::mock(ProductionCommandGatewayInterface::class);
        $parser = Mockery::mock(ZoneParserService::class);
        $after = $this->zone();
        $after['production_queues'] = [];
        $zones->shouldReceive('forAccount')->with($account, true)->once()->globally()->ordered()->andReturn(new ZoneSnapshot($this->zone()));
        $gateway->shouldReceive('deliverOrder')->with($account, 2)->once()->globally()->ordered()->andReturn('delivery');
        $parser->shouldReceive('parse')->with('delivery')->once()->andReturn(['server_response_confirmed' => true, 'errorCode' => 0]);
        $zones->shouldReceive('forAccount')->with($account, true)->once()->globally()->ordered()->andReturn(new ZoneSnapshot($after));
        $gateway->shouldReceive('queueOrder')->with($account, 44, 2, 'Tome', 1, 1)->once()->globally()->ordered()->andReturn('queued');
        $parser->shouldReceive('parse')->with('queued')->once()->andReturn(['server_response_confirmed' => true, 'errorCode' => 0]);
        $confirmed = $this->zone(false);
        $zones->shouldReceive('forAccount')->with($account, true)->once()->globally()->ordered()->andReturn(new ZoneSnapshot($confirmed));
        $handler = new ProduceBuffHandler($zones, new ProductionOrderPolicy(new ConfigProductionCatalog($this->catalog())), $gateway, $parser);
        $this->assertSame(__('tasks.produce_buff.queued', ['recipe' => 'Tome', 'amount' => 1, 'grid' => 44]), $handler->handle($account, $this->payload()));
    }

    public function test_bookbinder_does_not_use_elapsed_time_as_readiness(): void
    {
        $account = $this->account();
        $zones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $gateway = Mockery::mock(ProductionCommandGatewayInterface::class);
        $parser = Mockery::mock(ZoneParserService::class);
        $zones->shouldReceive('forAccount')->with($account, true)->once()->andReturn(new ZoneSnapshot($this->zone(false)));
        $gateway->shouldNotReceive('deliverOrder');
        $gateway->shouldNotReceive('queueOrder');
        $handler = new ProduceBuffHandler($zones, new ProductionOrderPolicy(new ConfigProductionCatalog($this->catalog())), $gateway, $parser);
        $this->assertSame(__('tasks.produce_buff.busy'), $handler->handle($account, $this->payload()));
    }

    /** @return array<string,array{string}> */
    public static function unconfirmedCases(): array
    {
        return ['unknown_ack' => ['unknown_ack'], 'still_queued' => ['still_queued'], 'missing_queue' => ['missing_queue'], 'untrusted_refresh' => ['untrusted_refresh'], 'refresh_exception' => ['refresh_exception'], 'error_refresh' => ['error_refresh']];
    }

    #[DataProvider('unconfirmedCases')]
    public function test_bookbinder_never_starts_after_unconfirmed_collection(string $case): void
    {
        $account = $this->account();
        $zones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $gateway = Mockery::mock(ProductionCommandGatewayInterface::class);
        $parser = Mockery::mock(ZoneParserService::class);
        $zones->shouldReceive('forAccount')->with($account, true)->once()->andReturn(new ZoneSnapshot($this->zone()));
        $gateway->shouldReceive('deliverOrder')->with($account, 2)->once()->andReturn('delivery');
        $parser->shouldReceive('parse')->with('delivery')->once()->andReturn($case === 'unknown_ack' ? [] : ['server_response_confirmed' => true, 'errorCode' => 0]);
        if ($case !== 'unknown_ack') {
            $after = $this->zone();
            if ($case === 'error_refresh') {
                $after['production_queues'] = [];
                $after['errorCode'] = 1005;
            }
            if ($case === 'missing_queue') {
                unset($after['production_queues']);
            }
            if ($case === 'untrusted_refresh') {
                $after['production_queues'] = [];
                unset($after['server_response_confirmed']);
            }
            $expectation = $zones->shouldReceive('forAccount')->with($account, true)->once();
            if ($case === 'refresh_exception') {
                $expectation->andThrow(new \RuntimeException('session lost'));
            } else {
                $expectation->andReturn(new ZoneSnapshot($after));
            }
        }
        $gateway->shouldNotReceive('queueOrder');
        $handler = new ProduceBuffHandler($zones, new ProductionOrderPolicy(new ConfigProductionCatalog($this->catalog())), $gateway, $parser);
        $result = $handler->handle($account, $this->payload());
        $this->assertNotSame(__('tasks.produce_buff.queued', ['recipe' => 'Tome', 'amount' => 1, 'grid' => 44]), $result);
    }

    public function test_shared_account_lock_prevents_duplicate_actions(): void
    {
        $account = $this->account();
        $lock = Cache::lock('reward-production:account:'.$account->id, 600);
        $this->assertTrue($lock->get());
        try {
            $parser = Mockery::mock(ZoneParserService::class);
            $handler = new CollectDailyLoginRewardHandler(Mockery::mock(TsoAmfService::class), $parser, new DailyLoginQuestSelector, new CommandResponseDecoder($parser));
            $this->assertSame(__('tasks.daily_login.busy'), $handler->handle($account, ['scope' => 'self']));
        } finally {
            $lock->release();
        }
    }

    /** @return array<string,array{int}> */
    public static function sessionCodes(): array
    {
        return ['expired' => [1005], 'conflict' => [1012]];
    }

    #[DataProvider('sessionCodes')]
    public function test_initial_session_error_remains_retryable_without_side_effects(int $code): void
    {
        $account = $this->account();
        $zones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $gateway = Mockery::mock(ProductionCommandGatewayInterface::class);
        $data = $this->zone();
        $data['errorCode'] = $code;
        $zones->shouldReceive('forAccount')->with($account, true)->once()->andReturn(new ZoneSnapshot($data));
        $gateway->shouldNotReceive('deliverOrder');
        $gateway->shouldNotReceive('queueOrder');
        $handler = new ProduceBuffHandler($zones, new ProductionOrderPolicy(new ConfigProductionCatalog($this->catalog())), $gateway, Mockery::mock(ZoneParserService::class));
        $this->expectException(GameServerErrorException::class);
        $handler->handle($account, $this->payload());
    }

    public function test_daily_selector_requires_visible_reward_and_strict_uid(): void
    {
        $selector = new DailyLoginQuestSelector;
        $quest = $this->quest();
        $quest['mQuestDefinition']['showRewardWindow'] = false;
        $this->assertFalse($selector->isClaimable($quest));
        $quest = $this->quest();
        $quest['mUniqueID']['uniqueID2'] = '123';
        $this->assertFalse($selector->isClaimable($quest));
    }

    /** @return array<string, array{string}> */
    public static function enqueueConfirmationCases(): array
    {
        return ['empty' => ['empty'], 'delayed' => ['delayed'], 'wrong_recipe' => ['wrong_recipe'], 'wrong_amount' => ['wrong_amount'], 'wrong_stacks' => ['wrong_stacks'], 'untrusted' => ['untrusted'], 'exception' => ['exception']];
    }

    #[DataProvider('enqueueConfirmationCases')]
    public function test_bookbinder_ack_requires_matching_fresh_order_without_resending(string $case): void
    {
        Sleep::fake();
        $account = $this->account();
        $zones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $gateway = Mockery::mock(ProductionCommandGatewayInterface::class);
        $parser = Mockery::mock(ZoneParserService::class);
        $before = $this->zone();
        $before['production_queues'] = [];
        $zones->shouldReceive('forAccount')->with($account, true)->once()->ordered()->andReturn(new ZoneSnapshot($before));
        $gateway->shouldNotReceive('deliverOrder');
        $gateway->shouldReceive('queueOrder')->with($account, 44, 2, 'Tome', 1, 1)->once()->andReturn('ack');
        $parser->shouldReceive('parse')->with('ack')->once()->andReturn(['server_response_confirmed' => true, 'errorCode' => 0]);
        $after = $this->zone(false);
        if ($case === 'empty') {
            $zones->shouldReceive('forAccount')->with($account, true)->times(3)->ordered()->andReturn(new ZoneSnapshot($before));
        } elseif ($case === 'exception') {
            $zones->shouldReceive('forAccount')->with($account, true)->once()->ordered()->andThrow(new \RuntimeException('fixture read failed'));
        } else {
            if ($case === 'delayed') {
                $zones->shouldReceive('forAccount')->with($account, true)->once()->ordered()->andReturn(new ZoneSnapshot($before));
            } elseif ($case === 'wrong_recipe') {
                $after['production_queues'][0]['orders'][0]['type_string'] = 'Codex';
            } elseif ($case === 'wrong_amount') {
                $after['production_queues'][0]['orders'][0]['amount'] = 2;
            } elseif ($case === 'wrong_stacks') {
                $after['production_queues'][0]['orders'][0]['stacks'] = 2;
            } elseif ($case === 'untrusted') {
                $after['server_response_confirmed'] = false;
            }
            $zones->shouldReceive('forAccount')->with($account, true)->once()->ordered()->andReturn(new ZoneSnapshot($after));
        }
        $handler = new ProduceBuffHandler($zones, new ProductionOrderPolicy(new ConfigProductionCatalog($this->catalog())), $gateway, $parser);
        $result = $handler->handleResult($account, $this->payload());
        $this->assertSame($case === 'delayed' ? 'completed' : 'failed', $result->status);
    }

    public function test_ready_book_is_collected_before_rechecking_the_new_tier(): void
    {
        $account = $this->account();
        $catalog = $this->catalog();
        $catalog['recipes'][2][0]['cost_is_lower_bound'] = true;
        $catalog['recipes'][2][0]['costs'] = [['resource' => 'Coin', 'count' => 10]];
        $catalog['recipes'][2][0]['cost_tiers'] = [
            ['threshold' => 0, 'costs' => [['resource' => 'Coin', 'count' => 10]]],
            ['threshold' => 10, 'costs' => [['resource' => 'Coin', 'count' => 11]]],
        ];
        $before = $this->zone();
        $before['resources'] = [['name' => 'Tome', 'amount' => 0, 'producedAmount' => 9], ['name' => 'Coin', 'amount' => 10]];
        $after = $before;
        $after['production_queues'] = [];
        $after['resources'][0]['amount'] = 1;
        $after['resources'][0]['producedAmount'] = 10;
        $zones = Mockery::mock(ZoneSnapshotProviderInterface::class);
        $zones->shouldReceive('forAccount')->with($account, true)->once()->globally()->ordered()->andReturn(new ZoneSnapshot($before));
        $gateway = Mockery::mock(ProductionCommandGatewayInterface::class);
        $gateway->shouldReceive('deliverOrder')->with($account, 2)->once()->globally()->ordered()->andReturn('delivery');
        $zones->shouldReceive('forAccount')->with($account, true)->once()->globally()->ordered()->andReturn(new ZoneSnapshot($after));
        $gateway->shouldNotReceive('queueOrder');
        $parser = Mockery::mock(ZoneParserService::class);
        $parser->shouldReceive('parse')->with('delivery')->once()->andReturn(['server_response_confirmed' => true, 'errorCode' => 0]);
        $result = (new ProduceBuffHandler($zones, new ProductionOrderPolicy(new ConfigProductionCatalog($catalog)), $gateway, $parser))->handleResult($account, $this->payload());
        $this->assertSame('skipped', $result->status);
        $this->assertSame(__('tasks.produce_buff.rejected.insufficient_resources', ['grid' => 44, 'recipe' => 'Tome', 'type' => 2]), $result->message);
    }
}
