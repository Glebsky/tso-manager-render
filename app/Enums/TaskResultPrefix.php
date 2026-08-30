<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskResultPrefix: string
{
    case Ok = 'OK';
    case Error = 'ERROR';
    case Skipped = 'SKIPPED';
    case Resumed = 'RESUMED';
    case Partial = 'PARTIAL';
    case Failed = 'FAILED';
    case Warning = 'WARNING';

    public function format(string $message = ''): string
    {
        return $message !== '' ? "{$this->value}: {$message}" : "{$this->value}:";
    }
}
