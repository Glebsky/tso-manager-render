<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Game\Commands\AccountRewardProductionLock;
use App\Services\ZoneParserService;
use App\Support\Zone\ZoneSnapshot;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

final class RewardSafetyTest extends TestCase
{
    public function test_release_exception_cannot_escape_after_action(): void
    {
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('get')->once()->andReturn(true);
        $lock->shouldReceive('release')->once()->andThrow(new \RuntimeException('cache unavailable'));
        Cache::shouldReceive('lock')->with('reward-production:account:3', 600)->once()->andReturn($lock);
        $calls = 0;
        $result = (new AccountRewardProductionLock)->run(3, function () use (&$calls): string {
            $calls++;

            return 'success';
        }, 'busy', 'unknown');
        $this->assertSame('unknown', $result);
        $this->assertSame(1, $calls);
    }

    public function test_malformed_orders_are_unknown_not_ready_or_empty(): void
    {
        foreach ([['amount' => 1, 'produced_items' => 'garbage'], ['amount' => 0, 'produced_items' => 1], ['amount' => 1], 'bad'] as $order) {
            $snapshot = new ZoneSnapshot(['production_queues' => [['production_type' => 2, 'orders' => [$order]]]]);
            $this->assertNull($snapshot->productionQueueFor(2));
        }
    }

    public function test_nested_success_response_cannot_override_top_level_error(): void
    {
        $data = app(ZoneParserService::class)->parse((string) file_get_contents(base_path('tests/Fixtures/Amf/response_nested_conflict.bin')));
        $this->assertTrue($data['server_response_confirmed']);
        $this->assertSame(1005, $data['errorCode']);
    }

    public function test_multiple_response_envelopes_are_not_confirmed(): void
    {
        $data = app(ZoneParserService::class)->parse((string) file_get_contents(base_path('tests/Fixtures/Amf/response_multiple.bin')));
        $this->assertFalse($data['server_response_confirmed']);
    }
}
