<?php

declare(strict_types=1);

namespace App\Exceptions;

class FriendZoneLoadException extends TaskExecutionException
{
    public function __construct(int $err, string $errMsg)
    {
        parent::__construct('error.friend_zone_failed', [
            'err' => $err,
            'errMsg' => $errMsg,
        ], 502);
    }
}
