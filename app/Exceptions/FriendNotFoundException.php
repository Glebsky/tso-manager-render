<?php

declare(strict_types=1);

namespace App\Exceptions;

class FriendNotFoundException extends TaskExecutionException
{
    public function __construct()
    {
        parent::__construct('error.friend_not_found', [], 404);
    }
}
