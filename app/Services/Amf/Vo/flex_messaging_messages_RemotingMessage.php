<?php

declare(strict_types=1);

namespace App\Services\Amf\Vo;

class flex_messaging_messages_RemotingMessage
{
    public ?string $destination = null;

    public ?string $operation = null;

    public ?string $source = null;

    public int $timestamp = 0;

    public int $timeToLive = 0;

    public ?string $messageId = null;

    public ?string $clientId = null;

    public mixed $headers = null;

    public mixed $body = null;
}
