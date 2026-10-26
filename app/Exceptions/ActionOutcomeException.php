<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** A reported business failure, never a transport/session retry signal. */
final class ActionOutcomeException extends RuntimeException {}
