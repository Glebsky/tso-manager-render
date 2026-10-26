<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Exceptions\Contracts\SkippableStepExceptionInterface;
use RuntimeException;

final class ActionSkippedException extends RuntimeException implements SkippableStepExceptionInterface
{
    public function getSkipReason(): string
    {
        return $this->getMessage();
    }
}
