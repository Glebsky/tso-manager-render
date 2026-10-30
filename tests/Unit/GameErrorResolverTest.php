<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GameErrorResolver;
use Tests\TestCase;

final class GameErrorResolverTest extends TestCase
{
    public function test_it_resolves_known_error_code(): void
    {
        $messageEn = GameErrorResolver::getMessage(1001, 'en');
        $this->assertSame('Failed to initialize zone', $messageEn);

        $messageRu = GameErrorResolver::getMessage(1001, 'ru');
        $this->assertSame('Не удалось инициализировать зону', $messageRu);

        $messageUk = GameErrorResolver::getMessage(1001, 'uk');
        $this->assertSame('Не вдалося ініціалізувати зону', $messageUk);
    }

    public function test_it_replaces_code_placeholder_for_unknown_error(): void
    {
        $messageEn = GameErrorResolver::getMessage(9999, 'en');
        $this->assertSame('Unknown game error (9999)', $messageEn);
        $this->assertStringNotContainsString('{code}', $messageEn);
        $this->assertStringNotContainsString(':code', $messageEn);

        $messageRu = GameErrorResolver::getMessage(8888, 'ru');
        $this->assertSame('Неизвестная ошибка игры (8888)', $messageRu);
        $this->assertStringNotContainsString('{code}', $messageRu);
        $this->assertStringNotContainsString(':code', $messageRu);

        $messageUk = GameErrorResolver::getMessage(7777, 'uk');
        $this->assertSame('Невідома помилка гри (7777)', $messageUk);
        $this->assertStringNotContainsString('{code}', $messageUk);
        $this->assertStringNotContainsString(':code', $messageUk);
    }
}
