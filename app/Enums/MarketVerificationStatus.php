<?php

declare(strict_types=1);

namespace App\Enums;

enum MarketVerificationStatus: string
{
    case Unverified = 'unverified';
    case Verified = 'verified';
    case Mismatch = 'mismatch';
    case Error = 'error';
}
