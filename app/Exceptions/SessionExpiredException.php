<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the game server explicitly rejects the stored web session
 * (dsoAuthUser + dsoAuthToken + cookie jar) and a full re-login is required.
 *
 * Deliberately extends RuntimeException and NOT TaskExecutionException: this is
 * a transport-level signal, every existing call site already catches Exception,
 * and it must not be rendered as a user-facing task payload.
 */
final class SessionExpiredException extends RuntimeException {}
