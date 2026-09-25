<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The login flow hit a challenge only a human can solve (CAPTCHA, 2FA) or
 * the account is under the cooldown such a challenge leaves behind.
 *
 * Retrying automatically cannot succeed, so sync jobs fail immediately
 * instead of burning their retry budget (A-13, UnrecoverableAuthError).
 */
final class AuthChallengeException extends RuntimeException
{
    public static function captcha(): self
    {
        return new self((string) __('ui.auth.captcha_required'));
    }

    public static function twoFactor(): self
    {
        return new self((string) __('ui.auth.2fa_required'));
    }

    /**
     * The message stored by a previous challenge (`account_login_cooldown:*`).
     */
    public static function cooldown(string $reason): self
    {
        return new self($reason !== '' ? $reason : (string) __('ui.auth.captcha_required'));
    }
}
