<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Mines;

use App\Models\Account;
use App\Services\Game\Mines\AmfMineCommandGateway;
use App\Services\TsoAmfService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class AmfMineCommandGatewayTest extends TestCase
{
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = new Account([
            'username' => 'test_user',
            'password' => 'secret',
            'server' => 'ru_1',
            'region' => 'ru',
        ]);
        $this->account->id = 1;
    }

    public function test_it_sends_command_50_with_building_number_in_the_type_field(): void
    {
        /** @var TsoAmfService&MockInterface $mockAmf */
        $mockAmf = Mockery::mock(TsoAmfService::class);
        $mockAmf->expects('buildBuilding')
            ->with($this->account, 50, 6431)
            ->andReturn('dummy_response');

        $gateway = new AmfMineCommandGateway($mockAmf);
        $result = $gateway->buildMine($this->account, 50, 6431);

        $this->assertSame('dummy_response', $result);
    }

    public function test_it_sends_command_60_with_zero_type(): void
    {
        /** @var TsoAmfService&MockInterface $mockAmf */
        $mockAmf = Mockery::mock(TsoAmfService::class);
        $mockAmf->expects('upgradeBuilding')
            ->with($this->account, 6431)
            ->andReturn('dummy_response');

        $gateway = new AmfMineCommandGateway($mockAmf);
        $result = $gateway->upgradeMine($this->account, 6431);

        $this->assertSame('dummy_response', $result);
    }

    public function test_it_sends_exactly_one_call_per_invocation(): void
    {
        /** @var TsoAmfService&MockInterface $mockAmf */
        $mockAmf = Mockery::mock(TsoAmfService::class);
        $mockAmf->expects('buildBuilding')
            ->andReturn('ok');

        $gateway = new AmfMineCommandGateway($mockAmf);
        $gateway->buildMine($this->account, 36, 1234);
    }
}
