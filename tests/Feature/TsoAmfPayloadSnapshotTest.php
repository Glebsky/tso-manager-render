<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Amf\Amf3Encoder;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dGetFriendsVO;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dServerCall;
use App\Services\Amf\Vo\flex_messaging_messages_RemotingMessage;
use Tests\TestCase;

class TsoAmfPayloadSnapshotTest extends TestCase
{
    public function test_amf3_encoder_produces_deterministic_payload_structure(): void
    {
        $getFriends = new defaultGame_Communication_VO_dGetFriendsVO;
        $getFriends->version = 'fe5e82453230b4145854f220221b9360f33dec92';

        $call = new defaultGame_Communication_VO_dServerCall;
        $call->dsoAuthToken = 'token_secret_123';
        $call->dsoAuthUser = 1234567;
        $call->zoneID = 1234567;
        $call->type = 1014;
        $call->dsoAuthRandomClientID = 999999;
        $call->data = $getFriends;

        $msg = new flex_messaging_messages_RemotingMessage;
        $msg->destination = 'PLAYER';
        $msg->operation = 'GetFriends';
        $msg->source = 'com.bluebyte.game.servlet.PlayerHandler';
        $msg->messageId = '11111111-2222-3333-4444-555555555555';
        $msg->headers = (object) ['DSId' => 'nil', 'DSEndpoint' => 'SMC-Endpoint'];
        $msg->body = [$call];

        $encoder = new Amf3Encoder;
        $encoder->encode([$msg]);
        $bytes = $encoder->getOutput();

        $hex = bin2hex($bytes);
        $fixturePath = base_path('tests/Fixtures/Amf/get_friends.hex');

        if (! file_exists(dirname($fixturePath))) {
            mkdir(dirname($fixturePath), 0777, true);
        }

        if (! file_exists($fixturePath)) {
            file_put_contents($fixturePath, $hex);
        }

        $expectedHex = file_get_contents($fixturePath);
        $this->assertSame($expectedHex, $hex, 'Encoded AMF payload hex must match the golden fixture byte-for-byte.');
    }
}
