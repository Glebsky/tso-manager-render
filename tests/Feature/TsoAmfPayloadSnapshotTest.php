<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Amf\Amf3Encoder;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dGetFriendsVO;
use App\Services\Amf\Vo\defaultGame_Communication_VO_dServerAction;
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

    public function test_building_selected_quest_trigger_payload_structure(): void
    {
        $action = new defaultGame_Communication_VO_dServerAction;
        $action->type = 2; // QUEST_STACK_BUILDING_SELECTED
        $action->grid = 0;
        $action->endGrid = 0;
        $action->data = 7215;

        $call = new defaultGame_Communication_VO_dServerCall;
        $call->dsoAuthToken = 'token_secret_123';
        $call->dsoAuthUser = 1234567;
        $call->zoneID = 1234567;
        $call->type = 100; // CMD_QUEST_TRIGGER
        $call->dsoAuthRandomClientID = 999999;
        $call->data = $action;

        $msg = new flex_messaging_messages_RemotingMessage;
        $msg->destination = 'SMC';
        $msg->operation = 'ExecuteServerCall';
        $msg->source = 'com.bluebyte.game.servlet.EventHandler';
        $msg->messageId = '11111111-2222-3333-4444-555555555555';
        $msg->headers = (object) ['DSId' => 'nil', 'DSEndpoint' => 'SMC-Endpoint'];
        $msg->body = [$call];

        $encoder = new Amf3Encoder;
        $encoder->encode([$msg]);
        $bytes = $encoder->getOutput();

        $hex = bin2hex($bytes);
        $fixturePath = base_path('tests/Fixtures/Amf/quest_trigger_building_selected.hex');

        if (! file_exists(dirname($fixturePath))) {
            mkdir(dirname($fixturePath), 0777, true);
        }

        if (! file_exists($fixturePath)) {
            file_put_contents($fixturePath, $hex);
        }

        $expectedHex = file_get_contents($fixturePath);
        $this->assertSame($expectedHex, $hex, 'Encoded QuestTrigger AMF payload hex must match the golden fixture byte-for-byte.');
        $this->assertSame(100, $call->type);
        $this->assertSame(2, $action->type);
        $this->assertSame(0, $action->grid);
        $this->assertSame(0, $action->endGrid);
        $this->assertSame(7215, $action->data);
    }

    public function test_collect_collectible_payload_structure(): void
    {
        $action = new defaultGame_Communication_VO_dServerAction;
        $action->type = 0;
        $action->grid = 7215;
        $action->endGrid = 0;
        $action->data = 'cCollectibleBuilding';

        $call = new defaultGame_Communication_VO_dServerCall;
        $call->dsoAuthToken = 'token_secret_123';
        $call->dsoAuthUser = 1234567;
        $call->zoneID = 1234567;
        $call->type = 65; // CMD_DESTRUCT_BUILDING
        $call->dsoAuthRandomClientID = 999999;
        $call->data = $action;

        $msg = new flex_messaging_messages_RemotingMessage;
        $msg->destination = 'SMC';
        $msg->operation = 'ExecuteServerCall';
        $msg->source = 'com.bluebyte.game.servlet.EventHandler';
        $msg->messageId = '11111111-2222-3333-4444-555555555555';
        $msg->headers = (object) ['DSId' => 'nil', 'DSEndpoint' => 'SMC-Endpoint'];
        $msg->body = [$call];

        $encoder = new Amf3Encoder;
        $encoder->encode([$msg]);
        $bytes = $encoder->getOutput();

        $hex = bin2hex($bytes);
        $fixturePath = base_path('tests/Fixtures/Amf/collect_collectible.hex');

        if (! file_exists(dirname($fixturePath))) {
            mkdir(dirname($fixturePath), 0777, true);
        }

        if (! file_exists($fixturePath)) {
            file_put_contents($fixturePath, $hex);
        }

        $expectedHex = file_get_contents($fixturePath);
        $this->assertSame($expectedHex, $hex, 'Encoded Collectible AMF payload hex must match the golden fixture byte-for-byte.');
        $this->assertSame(65, $call->type);
        $this->assertSame(0, $action->type);
        $this->assertSame(7215, $action->grid);
        $this->assertSame(0, $action->endGrid);
        $this->assertSame('cCollectibleBuilding', $action->data);
    }
}
