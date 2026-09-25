<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Http\TsoCurlHelper;
use CurlHandle;
use Tests\TestCase;

class TsoCurlHelperTest extends TestCase
{
    public function test_default_flash_headers_contain_standard_fingerprint(): void
    {
        $headers = TsoCurlHelper::defaultFlashHeaders();

        $this->assertContains('User-Agent: '.TsoCurlHelper::FLASH_USER_AGENT, $headers);
        $this->assertContains('Referer: '.TsoCurlHelper::FLASH_REFERER, $headers);
    }

    public function test_init_returns_configured_curl_handle(): void
    {
        $ch = TsoCurlHelper::init('https://example.com', [
            CURLOPT_HTTPGET => true,
        ]);

        $this->assertInstanceOf(CurlHandle::class, $ch);
    }
}
