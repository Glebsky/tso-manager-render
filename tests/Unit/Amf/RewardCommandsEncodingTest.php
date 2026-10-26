<?php

declare(strict_types=1);

namespace Tests\Unit\Amf;

use App\Models\Account;
use App\Services\Amf\Amf3Encoder;
use App\Services\Amf\Transport\TsoClientInterface;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dServerAction;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dServerCall;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dUniqueID;
use App\Services\Game\Session\GameClientIdentity;
use App\Services\Tasks\Execution\GameResponseValidator;
use App\Services\TsoAmfService;
use Mockery;
use Tests\TestCase;

final class RewardCommandsEncodingTest extends TestCase
{
    public function test_delivery_sends_primitive_integer_two_to_home_zone(): void
    {
        $client = Mockery::mock(TsoClientInterface::class);
        $account = new Account;
        $account->id = 1;
        $account->dso_auth_user = '1234';
        $client->shouldReceive('sendCommand')->once()->withArgs(function (Account $actual, defaultGame_Communication_VO_dServerCall $call) use ($account): bool {
            $this->assertSame($account, $actual);
            $this->assertSame(141, $call->type);
            $this->assertSame(1234, $call->zoneID);
            $this->assertSame(2, $call->data);
            $encoder = new Amf3Encoder;
            $encoder->encode($call->data);
            $this->assertSame("\x04\x02", $encoder->getOutput());

            return true;
        })->andReturn('wire');
        $service = new TsoAmfService($client, app(GameResponseValidator::class), new GameClientIdentity);
        $this->assertSame('wire', $service->deliverProduction($account, 2));
    }

    public function test_daily_accept_uses_typed_uid_not_grid_or_quest_name(): void
    {
        $client = Mockery::mock(TsoClientInterface::class);
        $account = new Account;
        $account->id = 1;
        $account->dso_auth_user = '1234';
        $client->shouldReceive('sendCommand')->once()->withArgs(function (Account $actual, defaultGame_Communication_VO_dServerCall $call): bool {
            $this->assertSame(100, $call->type);
            $this->assertSame(1234, $call->zoneID);
            $this->assertInstanceOf(defaultGame_Communication_VO_dServerAction::class, $call->data);
            $this->assertSame(1, $call->data->type);
            $this->assertInstanceOf(defaultGame_Communication_VO_dUniqueID::class, $call->data->data);
            $this->assertSame(0, $call->data->data->uniqueID1);
            $this->assertSame(123, $call->data->data->uniqueID2);

            return true;
        })->andReturn('wire');
        $service = new TsoAmfService($client, app(GameResponseValidator::class), new GameClientIdentity);
        $this->assertSame('wire', $service->sendDailyLoginRewardOk($account, 0, 123));
    }
}
