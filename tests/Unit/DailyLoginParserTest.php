<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Game\Commands\CommandResponseDecoder;
use App\Services\Game\Quests\DailyLoginQuestSelector;
use App\Services\ZoneParserService;
use Mockery;
use Tests\TestCase;

final class DailyLoginParserTest extends TestCase
{
    public function test_real_parser_keeps_typed_daily_login_quest_and_uid(): void
    {
        $data = app(ZoneParserService::class)->parse((string) file_get_contents(base_path('tests/Fixtures/Amf/daily_login_pending.bin')));
        $this->assertTrue($data['server_response_confirmed']);
        $this->assertSame(0, $data['errorCode']);
        $selector = new DailyLoginQuestSelector;
        $quests = $selector->quests($data);
        $this->assertNotNull($quests);
        $this->assertCount(1, $quests);
        $this->assertTrue($selector->isClaimable($quests[0]));
        $this->assertSame([0, 123], $selector->identity($quests[0]));
    }

    public function test_real_parser_preserves_explicit_empty_pool(): void
    {
        $data = app(ZoneParserService::class)->parse((string) file_get_contents(base_path('tests/Fixtures/Amf/daily_login_empty.bin')));
        $this->assertSame([], (new DailyLoginQuestSelector)->quests($data));
    }

    public function test_real_parser_does_not_treat_malformed_pool_as_empty(): void
    {
        $data = app(ZoneParserService::class)->parse((string) file_get_contents(base_path('tests/Fixtures/Amf/daily_login_malformed.bin')));
        $this->assertNull((new DailyLoginQuestSelector)->quests($data));
    }

    public function test_decoder_never_infers_success_from_default_error_code(): void
    {
        $parser = Mockery::mock(ZoneParserService::class);
        $parser->shouldReceive('parse')->with('unknown')->once()->andReturn(['errorCode' => 0]);
        $parser->shouldReceive('parse')->with('broken')->once()->andThrow(new \RuntimeException('decode'));
        $decoder = new CommandResponseDecoder($parser);
        $this->assertNull($decoder->errorCode('unknown'));
        $this->assertNull($decoder->errorCode('broken'));
    }
}
