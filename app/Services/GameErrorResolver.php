<?php

declare(strict_types=1);

namespace App\Services;

class GameErrorResolver
{
    /**
     * Get user-friendly error message for game server error code in active locale.
     */
    public static function getMessage(int $errorCode, ?string $locale = null): string
    {
        $key = "ui.game_error.{$errorCode}";
        $translation = __($key, [], $locale);

        if (is_string($translation) && $translation !== $key) {
            return $translation;
        }

        return __('ui.game_error.unknown', ['code' => $errorCode], $locale);
    }
}
