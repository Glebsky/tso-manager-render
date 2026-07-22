<?php

declare(strict_types=1);

namespace App\Exceptions;

class GameServerErrorException extends TaskExecutionException
{
    public function __construct(int $errorCode, string $errorMsg)
    {
        parent::__construct('error.server_error', [
            'errorCode' => $errorCode,
            'errorMsg' => $errorMsg,
        ]);
    }
}
