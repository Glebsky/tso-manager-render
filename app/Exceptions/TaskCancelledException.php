<?php

declare(strict_types=1);

namespace App\Exceptions;

class TaskCancelledException extends TaskExecutionException
{
    public function __construct(int $taskId)
    {
        parent::__construct('error.task_cancelled', ['id' => $taskId]);
    }
}
