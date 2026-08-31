<?php

declare(strict_types=1);

namespace Tests\Unit\Amf;

use App\Models\Account;
use App\Services\Amf\Amf3Encoder;
use App\Services\Amf\Transport\TsoClientInterface;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dServerCall;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dTimedProductionVO;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use Mockery;
use Tests\TestCase;

final class StartTimedProductionEncodingTest extends TestCase
{
    public function test_it_encodes_timed_production_vo_with_14_fields_and_exact_traits_order(): void
    {
        $vo = new defaultGame_Communication_VO_dTimedProductionVO;
        $vo->productionType = 1;
        $vo->type_string = 'ProductivityBuffLvl3';
        $vo->amount = 3;
        $vo->stacks = 1;
        $vo->buildingGrid = 1234;

        $encoder = new Amf3Encoder;
        $encoder->encode($vo);
        $encoded = $encoder->getOutput();

        // 1. Alias in AMF
        $this->assertStringContainsString('defaultGame.Communication.VO.dTimedProductionVO', $encoded);

        // 2. NO dServerAction wrapping
        $this->assertStringNotContainsString('dServerAction', $encoded);

        // 3. Exact field name type_string with underscore
        $this->assertStringContainsString('type_string', $encoded);
        $this->assertStringNotContainsString('typeString', $encoded);

        // 4. Exact traits order of all 14 fields
        $expectedTraitsOrder = [
            'uniqueId',
            'playerId',
            'productionType',
            'type_string',
            'amount',
            'producedItems',
            'collectedTime',
            'modifiedProductionAdder',
            'modifiedProductionMultiplier',
            'modifiedInstantFinishCostAdder',
            'modifiedInstantFinishCostMultiplier',
            'stacks',
            'index',
            'buildingGrid',
        ];

        $lastPos = 0;
        foreach ($expectedTraitsOrder as $trait) {
            $pos = strpos($encoded, $trait, $lastPos);
            $this->assertNotFalse($pos, "Trait {$trait} not found in encoded payload");
            $this->assertGreaterThanOrEqual($lastPos, $pos, "Trait {$trait} is out of order");
            $lastPos = $pos + strlen($trait);
        }

        // 5. Ensure 0x05 markers exist for double fields (collectedTime, modifiedProductionMultiplier, modifiedInstantFinishCostMultiplier)
        $doubleMarkerCount = substr_count($encoded, chr(0x05));
        $this->assertGreaterThanOrEqual(3, $doubleMarkerCount, 'Expected at least 3 double (0x05) encoded fields');
    }

    public function test_queue_timed_production_sends_command_91_with_direct_vo(): void
    {
        $authMock = Mockery::mock(TsoAuthService::class);
        $clientMock = Mockery::mock(TsoClientInterface::class);

        $account = new Account;
        $account->id = 1;
        $account->dso_auth_user = '1601416';

        $clientMock->shouldReceive('sendCommand')
            ->once()
            ->withArgs(function (Account $acc, defaultGame_Communication_VO_dServerCall $call) use ($account) {
                return $acc === $account
                    && $call->type === 91
                    && $call->data instanceof defaultGame_Communication_VO_dTimedProductionVO
                    && $call->data->productionType === 1
                    && $call->data->type_string === 'ProductivityBuffLvl3'
                    && $call->data->amount === 3
                    && $call->data->stacks === 1
                    && $call->data->buildingGrid === 1234;
            })
            ->andReturn('dummy_amf_response');

        $amfService = new TsoAmfService($authMock, $clientMock);

        $response = $amfService->queueTimedProduction(
            account: $account,
            grid: 1234,
            productionType: 1,
            typeString: 'ProductivityBuffLvl3',
            amount: 3,
            stacks: 1,
        );

        $this->assertSame('dummy_amf_response', $response);
    }
}
