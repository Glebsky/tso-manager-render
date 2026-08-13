<?php

declare(strict_types=1);

namespace App\Exceptions;

class FriendZoneLoadException extends GameServerErrorException
{
    public function __construct(int $err, string $errMsg)
    {
        parent::__construct($err, $errMsg);

        $this->translationKey = 'error.friend_zone_failed';
        $this->params = [
            'err' => $err,
            'errMsg' => $errMsg,
        ];

        $fullKey = 'tasks.error.friend_zone_failed';
        $localizedMessage = __($fullKey, $this->params);
        if (! is_string($localizedMessage) || $localizedMessage === $fullKey) {
            $localizedMessage = "Failed to load friend's zone (server error code {$err}: {$errMsg})";
        }
        $this->message = $localizedMessage;
    }
}
