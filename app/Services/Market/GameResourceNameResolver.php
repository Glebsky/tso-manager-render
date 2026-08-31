<?php

declare(strict_types=1);

namespace App\Services\Market;

use App\Services\Lang\GameTranslationResolver;
use App\Services\Market\Contracts\ResourceNameResolver;

/**
 * Default {@see ResourceNameResolver} implementation backed by the game
 * translation catalogue (`lang/<locale>/game.php`, section `RES`).
 */
final class GameResourceNameResolver implements ResourceNameResolver
{
    private const string SECTION = 'RES';

    public function __construct(private readonly GameTranslationResolver $translations) {}

    public function resolve(?string $itemId, ?string $legacyName = null): string
    {
        if ($itemId === null || $itemId === '') {
            return $legacyName ?? '';
        }

        $fallback = ($legacyName !== null && $legacyName !== '') ? $legacyName : null;

        return $this->translations->name(self::SECTION, $itemId, $fallback);
    }
}
