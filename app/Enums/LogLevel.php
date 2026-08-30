<?php

declare(strict_types=1);

namespace App\Enums;

enum LogLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
    case Success = 'success';
    case Debug = 'debug';
}
