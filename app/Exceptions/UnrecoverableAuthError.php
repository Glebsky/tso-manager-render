<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

/**
 * Single definition of "retrying will not help, a human must fix the
 * account" for the sync jobs and the account status (A-13).
 *
 * Decided by exception type along the `previous` chain, never by matching
 * message text:
 *  - AuthChallengeException — CAPTCHA / 2FA / login cooldown;
 *  - SessionExpiredException — the load server rejected the stored web
 *    session after the caller's own re-login attempts;
 *  - AccountSyncException with HTTP 401 — game error 1005 that survived the
 *    fetcher's re-logins.
 */
final class UnrecoverableAuthError
{
    public static function isUnrecoverable(Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof AuthChallengeException || $current instanceof SessionExpiredException) {
                return true;
            }

            if ($current instanceof AccountSyncException && $current->httpStatus() === 401) {
                return true;
            }
        }

        return false;
    }
}
