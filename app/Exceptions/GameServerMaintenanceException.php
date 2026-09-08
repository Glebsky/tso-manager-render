<?php

declare(strict_types=1);

namespace App\Exceptions;

use Throwable;

class GameServerMaintenanceException extends TaskExecutionException
{
    public function __construct(
        ?string $details = null,
        ?int $queuePos = null,
        ?int $queueSize = null,
        int $httpStatus = 503,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        if ($queuePos !== null && $queueSize !== null) {
            $translationKey = 'error.server_maintenance_queue';
            $params = ['pos' => $queuePos, 'size' => $queueSize];
        } elseif ($details !== null && trim($details) !== '') {
            $translationKey = 'error.server_maintenance_details';
            $params = ['details' => trim($details)];
        } else {
            $translationKey = 'error.server_maintenance';
            $params = [];
        }

        parent::__construct($translationKey, $params, $httpStatus, $code, $previous);
    }

    public static function fromResponse(int $status, string $body): self
    {
        parse_str($body, $parsed);
        if (
            isset($parsed['queuePos'], $parsed['queueSize'])
            && is_numeric($parsed['queuePos'])
            && is_numeric($parsed['queueSize'])
        ) {
            return new self(
                queuePos: (int) $parsed['queuePos'],
                queueSize: (int) $parsed['queueSize'],
            );
        }

        $clean = strip_tags(trim($body));
        if (strlen($clean) > 80) {
            $clean = substr($clean, 0, 77).'...';
        }

        $details = $clean !== '' ? $clean : "HTTP {$status}";

        return new self(details: $details);
    }
}
