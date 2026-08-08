<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when the parsed zone response contains no `pickups` key at all,
 * which normally means storage/app/parse_zone.py has not been patched to
 * extract dZoneVO.pickups yet.
 */
class PickupsUnavailableException extends TaskExecutionException
{
    public function __construct()
    {
        parent::__construct('error.pickups_unavailable', [], 422);
    }
}
