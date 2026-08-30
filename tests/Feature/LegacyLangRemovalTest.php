<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\LangController;
use App\Services\LangParserService;
use Tests\TestCase;

class LegacyLangRemovalTest extends TestCase
{
    public function test_legacy_lang_endpoints_are_removed(): void
    {
        $this->getJson('/api/lang/res')->assertStatus(404);
        $this->getJson('/api/public/market/lang/res')->assertStatus(404);
    }

    public function test_legacy_classes_are_removed(): void
    {
        $this->assertFalse(class_exists(LangController::class));
        $this->assertFalse(class_exists(LangParserService::class));
    }

    public function test_default_locale_is_english(): void
    {
        $this->assertSame('en', config('app.fallback_locale'));
    }
}
