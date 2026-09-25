<?php

declare(strict_types=1);

namespace App\Services\Game\Zone;

/**
 * Drops cached game-zone snapshots of an account so the next action reads
 * the live state (B-03: infrastructure no longer reaches into a concrete
 * task handler for this).
 */
interface ZoneCacheInvalidator
{
    /** Home island zone used by buff/production actions. */
    public function invalidateHomeZone(int $accountId): void;

    /** Every cached friend zone of the account (visitor session expired, re-login). */
    public function invalidateFriendZones(int $accountId): void;
}
