<?php

declare(strict_types=1);

namespace App\Exceptions;

class TaskAccountNotFoundException extends TaskExecutionException
{
    public function __construct(int $taskId)
    {
        parent::__construct('error.account_not_found', ['id' => $taskId], 404);
    }
}
