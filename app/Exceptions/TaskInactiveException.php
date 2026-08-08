<?php

declare(strict_types=1);

namespace App\Exceptions;

class TaskInactiveException extends TaskExecutionException
{
    public function __construct(int $taskId)
    {
        parent::__construct('error.task_inactive', ['id' => $taskId], 422);
    }
}
