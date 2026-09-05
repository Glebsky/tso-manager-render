<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class AuthDebugDumpRemovedTest extends TestCase
{
    public function test_auth_service_never_dumps_play_page_or_flash_vars(): void
    {
        $source = (string) file_get_contents(app_path('Services/TsoAuthService.php'));

        $this->assertStringNotContainsString('debug/play_page.html', $source);
        $this->assertStringNotContainsString('debug/flash_vars.json', $source);
        $this->assertStringNotContainsString("Storage::disk('local')", $source);
    }
}
