<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The per-account game-session lock could not be acquired within
 * `game.session_lock_wait_seconds`: another process is talking to the game
 * server on behalf of the same account right now (FR-008, INV-004).
 *
 * Retryable and NOT a session problem — the caller should wait and try
 * again, never re-login (a re-login would create yet another game session
 * and is exactly what produces error 1012).
 *
 * Extends RuntimeException, like SessionExpiredException: a transport-level
 * signal that must not be rendered as a user-facing task payload.
 */
final class SessionBusyException extends RuntimeException
{
    public static function forAccount(int $accountId, string $lockName, int $waitedSeconds): self
    {
        return new self(sprintf(
            'Game session for account #%d is busy (%s lock not acquired within %ds).',
            $accountId,
            $lockName,
            $waitedSeconds
        ));
    }
}
