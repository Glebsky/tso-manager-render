<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Sleep;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Self buffs check eligibility against the persisted home zone while it is
 * fresh instead of re-reading the whole zone from the game before every step
 * (outbound traffic), without skipping a building on stale data and without
 * swallowing session errors.
 */
class HomeZoneCacheBuffTest extends TestCase
{
    use RefreshDatabase;

    private const array BUFF_STACK = ['uniqueId1' => 11, 'uniqueId2' => 22, 'amount' => 10, 'buffName_string' => 'ProductivityBuffLvl3'];

    private MockInterface $authMock;

    private MockInterface $amfMock;

    /** @var list<string> raw home zones returned by getZone() before the default 'live_zone' */
    private array $zoneQueue = [];

    private int $zoneReads = 0;

    /** @var array<string, array<string, mixed>> parsed responses by raw response */
    private array $parsed = [];

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();

        $this->authMock = Mockery::mock(TsoAuthService::class);
        $this->amfMock = Mockery::mock(TsoAmfService::class);
        $parser = Mockery::mock(ZoneParserService::class);

        $this->app->instance(TsoAuthService::class, $this->authMock);
        $this->app->instance(TsoAmfService::class, $this->amfMock);
        $this->app->instance(ZoneParserService::class, $parser);

        $this->authMock->shouldReceive('ensureAuthenticated')->with(Mockery::any())->andReturnNull();
        $this->amfMock->shouldReceive('getZone')->andReturnUsing(function (): string {
            $this->zoneReads++;

            return array_shift($this->zoneQueue) ?? 'live_zone';
        });
        $parser->shouldReceive('parse')->andReturnUsing(
            fn (string $raw): array => $this->parsed[$raw] ?? ['errorCode' => 0],
        );
    }

    public function test_consecutive_self_buffs_reuse_the_fresh_persisted_home_zone(): void
    {
        $account = $this->account([self::building(1500), self::building(1600)], now()->subMinutes(5));
        $task = $this->buffSequence($account, [1500, 1600, 1500]);

        $this->amfMock->shouldReceive('applyBuff')->once()->with(Mockery::any(), 1500, 11, 22, 1)->andReturn('buff_ok');
        $this->amfMock->shouldReceive('applyBuff')->once()->with(Mockery::any(), 1600, 11, 22, 1)->andReturn('buff_ok');

        Artisan::call('tso:execute-tasks');

        $this->assertSame(['completed', 'completed', 'skipped'], $this->stepStatuses($task));
        $this->assertSame(0, $this->zoneReads, 'A home zone younger than the TTL must not be re-read; our own buff is final.');
    }

    public function test_stale_home_zone_is_read_live_once_and_reused_by_the_next_steps(): void
    {
        $account = $this->account([self::building(1500), self::building(1600)], now()->subMinutes(20));
        $this->parsed['live_zone'] = $this->liveZone([self::building(1500), self::building(1600)]);
        $task = $this->buffSequence($account, [1500, 1600]);

        $this->amfMock->shouldReceive('applyBuff')->once()->with(Mockery::any(), 1500, 11, 22, 1)->andReturn('buff_ok');
        $this->amfMock->shouldReceive('applyBuff')->once()->with(Mockery::any(), 1600, 11, 22, 1)->andReturn('buff_ok');

        Artisan::call('tso:execute-tasks');

        $this->assertSame(['completed', 'completed'], $this->stepStatuses($task));
        $this->assertSame(1, $this->zoneReads);
        $account->refresh();
        $this->assertNotNull($account->last_sync_at);
        $this->assertTrue($account->last_sync_at->greaterThan(now()->subMinute()), 'The live read is persisted as the new cache.');
    }

    public function test_rejection_on_an_aged_cached_zone_is_rechecked_once_against_the_live_zone(): void
    {
        $expiredBuff = ['buffName_string' => 'ProductivityBuffLvl3', 'uniqueId' => 777, 'remaining' => 1200];
        $account = $this->account([self::building(1500, [$expiredBuff])], now()->subMinutes(5));
        $this->parsed['live_zone'] = $this->liveZone([self::building(1500)]);
        $task = $this->buffSequence($account, [1500]);

        $this->amfMock->shouldReceive('applyBuff')->once()->with(Mockery::any(), 1500, 11, 22, 1)->andReturn('buff_ok');

        Artisan::call('tso:execute-tasks');

        $this->assertSame(['completed'], $this->stepStatuses($task));
        $this->assertSame(1, $this->zoneReads, 'A rejection on a cached zone is confirmed by exactly one live read.');
        $account->refresh();
        $this->assertSame([], $account->zone_data['buildings'][0]['buffs'] ?? null);
    }

    public function test_rejection_on_a_young_cached_zone_skips_without_a_live_read(): void
    {
        $activeBuff = ['buffName_string' => 'ProductivityBuffLvl3', 'uniqueId' => 777, 'remaining' => 1200];
        $account = $this->account([self::building(1500, [$activeBuff])], now()->subSeconds(10));
        $task = $this->buffSequence($account, [1500]);

        $this->amfMock->shouldNotReceive('applyBuff');

        Artisan::call('tso:execute-tasks');

        $this->assertSame(['skipped'], $this->stepStatuses($task));
        $this->assertSame(0, $this->zoneReads);
    }

    public function test_superseded_session_on_the_buff_inventory_read_is_taken_over_not_reported_as_missing_buff(): void
    {
        $account = $this->account([self::building(1500)], now()->subMinutes(5), availableBuffs: []);
        $this->zoneQueue = ['zone_superseded'];
        $this->parsed['zone_superseded'] = ['errorCode' => 1012];
        $this->parsed['live_zone'] = $this->liveZone([self::building(1500)]);
        $task = $this->buffSequence($account, [1500]);

        $this->amfMock->shouldReceive('invalidateSession')->once()->with($account->id);
        $this->amfMock->shouldNotReceive('resetClient');
        $this->authMock->shouldNotReceive('resetSession');
        $this->amfMock->shouldReceive('applyBuff')->once()->with(Mockery::any(), 1500, 11, 22, 1)->andReturn('buff_ok');

        Artisan::call('tso:execute-tasks');

        $this->assertSame(['completed'], $this->stepStatuses($task));
    }

    public function test_superseded_session_on_the_home_zone_read_is_taken_over_instead_of_buffing_on_stale_data(): void
    {
        $account = $this->account([self::building(1500)], now()->subMinutes(20));
        $this->zoneQueue = ['zone_superseded'];
        $this->parsed['zone_superseded'] = ['errorCode' => 1012];
        $this->parsed['live_zone'] = $this->liveZone([self::building(1500)]);
        $task = $this->buffSequence($account, [1500]);

        $this->amfMock->shouldReceive('invalidateSession')->once()->with($account->id);
        $this->amfMock->shouldNotReceive('resetClient');
        $this->authMock->shouldNotReceive('resetSession');
        $this->amfMock->shouldReceive('applyBuff')->once()->with(Mockery::any(), 1500, 11, 22, 1)->andReturn('buff_ok');

        Artisan::call('tso:execute-tasks');

        $this->assertSame(['completed'], $this->stepStatuses($task));
        $this->assertGreaterThanOrEqual(2, $this->zoneReads, 'The zone is read again on the taken-over session.');
    }

    /**
     * @param  list<array<string, mixed>>  $buildings
     * @param  list<array<string, mixed>>|null  $availableBuffs
     */
    private function account(array $buildings, \DateTimeInterface $syncedAt, ?array $availableBuffs = null): Account
    {
        return Account::create([
            'username' => 'buffer',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'buffer',
            'dso_auth_user' => '10001',
            'last_sync_at' => $syncedAt,
            'zone_data' => [
                'friends' => [],
                'availableBuffs' => $availableBuffs ?? [self::BUFF_STACK],
                'buildings' => $buildings,
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $buildings
     * @return array<string, mixed>
     */
    private function liveZone(array $buildings): array
    {
        return ['errorCode' => 0, 'buildings' => $buildings, 'availableBuffs' => [self::BUFF_STACK]];
    }

    /**
     * @param  list<array<string, mixed>>  $buffs
     * @return array<string, mixed>
     */
    private static function building(int $grid, array $buffs = []): array
    {
        return ['buildingGrid' => $grid, 'buildingName_string' => 'Farm', 'buffs' => $buffs];
    }

    /**
     * @param  list<int>  $grids
     */
    private function buffSequence(Account $account, array $grids): ScheduledTask
    {
        return ScheduledTask::create([
            'account_id' => $account->id,
            'name' => 'Self buffs',
            'task_type' => 'sequence',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->subMinute(),
            'is_active' => true,
            'payload' => [
                'actions' => array_map(static fn (int $grid): array => [
                    'task_type' => 'apply_buff',
                    'delay_seconds' => 0,
                    'payload' => [
                        'target_scope' => 'self',
                        'grid' => $grid,
                        'unique_id1' => 11,
                        'unique_id2' => 22,
                        'buff_raw_name' => 'ProductivityBuffLvl3',
                        'amount' => 1,
                    ],
                ], $grids),
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function stepStatuses(ScheduledTask $task): array
    {
        $task->refresh();
        $steps = is_array($task->payload['step_results'] ?? null) ? $task->payload['step_results'] : [];

        return array_values(array_map(static fn (array $step): string => (string) ($step['status'] ?? ''), $steps));
    }
}
