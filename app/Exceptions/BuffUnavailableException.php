<?php

declare(strict_types=1);

namespace App\Exceptions;

class BuffUnavailableException extends TaskExecutionException
{
    public function __construct(string $buffName = '', int $uniqueId1 = 0, int $uniqueId2 = 0)
    {
        $buffDisplay = $buffName !== '' ? $buffName : "ID {$uniqueId1}-{$uniqueId2}";
        parent::__construct('error.buff_unavailable', ['buff' => $buffDisplay], 422);
    }
}
