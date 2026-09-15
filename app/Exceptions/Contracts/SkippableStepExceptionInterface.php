<?php

declare(strict_types=1);

namespace App\Exceptions\Contracts;

use Throwable;

interface SkippableStepExceptionInterface extends Throwable
{
    public function getSkipReason(): string;
}
