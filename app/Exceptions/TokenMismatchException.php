<?php

declare(strict_types=1);

namespace App\Exceptions;

class TokenMismatchException extends TaskExecutionException
{
    public function __construct(int $taskId, string $expectedToken, ?string $foundToken)
    {
        parent::__construct('error.token_mismatch', [
            'id' => $taskId,
            'expected' => $expectedToken,
            'found' => (string) $foundToken,
        ], 409);
    }
}
