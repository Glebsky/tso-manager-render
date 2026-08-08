<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Exceptions\Contracts\HasApiPresentation;
use Exception;
use Throwable;

class AccountSyncException extends Exception implements HasApiPresentation
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'Zone sync failed.',
        private readonly int $httpStatus = 500,
        private readonly array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function userMessage(): string
    {
        return $this->getMessage();
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
