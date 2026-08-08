<?php

declare(strict_types=1);

namespace App\Exceptions;

class UnknownTaskActionException extends TaskExecutionException
{
    public function __construct(string $taskType)
    {
        parent::__construct('error.unknown_action_type', [
            'taskType' => $taskType,
        ], 422);
    }
}
