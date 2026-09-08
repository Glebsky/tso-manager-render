<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Auth;

use App\Services\Auth\TsoPlayPageParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TsoPlayPageParserTest extends TestCase
{
    private TsoPlayPageParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new TsoPlayPageParser;
    }

    public function test_parses_valid_play_page_with_return_pattern(): void
    {
        $html = <<<'HTML'
        <html>
            <script>
                var loggedInUserName = 'SettlerMaster';
                function getFlashVars() {
                    return "dsoAuthToken=secret_token_123&dsoAuthUser=user_456&bb=https://r01.thesettlersonline.com/&zoneID=1001";
                }
            </script>
        </html>
        HTML;

        $result = $this->parser->parse($html);

        $this->assertSame('secret_token_123', $result['dsoAuthToken']);
        $this->assertSame('user_456', $result['dsoAuthUser']);
        $this->assertSame('https://r01.thesettlersonline.com/', $result['bburl']);
        $this->assertSame('1001', $result['zoneId']);
        $this->assertSame('SettlerMaster', $result['nickName']);
    }

    public function test_parses_valid_play_page_with_this_program_pattern(): void
    {
        $html = <<<'HTML'
        <html>
            <script>
                var config = {
                    thisProgram: "dsoAuthToken=another_token_789&dsoAuthUser=user_999&bb=https://r02.thesettlersonline.ru/"
                };
            </script>
        </html>
        HTML;

        $result = $this->parser->parse($html);

        $this->assertSame('another_token_789', $result['dsoAuthToken']);
        $this->assertSame('user_999', $result['dsoAuthUser']);
        $this->assertSame('https://r02.thesettlersonline.ru/', $result['bburl']);
        $this->assertNull($result['zoneId']);
        $this->assertSame('Unknown', $result['nickName']);
    }

    public function test_throws_exception_when_tokens_are_missing(): void
    {
        $html = '<html><body><h3>Maintenance in progress</h3></body></html>';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not extract auth tokens from play page');

        $this->parser->parse($html);
    }

    public function test_throws_exception_on_empty_string(): void
    {
        $this->expectException(RuntimeException::class);
        $this->parser->parse('');
    }
}
