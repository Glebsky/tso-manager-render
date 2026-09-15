<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Exceptions\Contracts\SkippableStepExceptionInterface;

class BuffUnavailableException extends TaskExecutionException implements SkippableStepExceptionInterface
{
    public function __construct(string $buffName = '', int $uniqueId1 = 0, int $uniqueId2 = 0)
    {
        $buffDisplay = $buffName !== '' ? $buffName : "ID {$uniqueId1}-{$uniqueId2}";
        parent::__construct('error.buff_unavailable', ['buff' => $buffDisplay], 422);
    }

    public function getSkipReason(): string
    {
        return $this->getMessage();
    }
}
