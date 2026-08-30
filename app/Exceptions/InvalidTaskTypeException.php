<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Exceptions\Contracts\HasApiPresentation;
use Exception;
use Throwable;

class InvalidTaskTypeException extends Exception implements HasApiPresentation
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'Invalid task type for action.',
        private readonly int $httpStatus = 422,
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
