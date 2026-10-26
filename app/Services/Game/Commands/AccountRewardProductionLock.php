<?php

declare(strict_types=1);

namespace App\Services\Game\Commands;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Serializes these compound actions independently of the transport's per-request lock. */
final readonly class AccountRewardProductionLock
{
    /**
     * @template T
     *
     * @param  callable():T  $action
     * @param  T  $busy
     * @param  T  $unknown
     * @return T
     */
    public function run(int $accountId, callable $action, mixed $busy, mixed $unknown): mixed
    {
        $lock = Cache::lock('reward-production:account:'.$accountId, 600);
        if (! $lock->get()) {
            return $busy;
        }
        try {
            $result = $action();
        } catch (Throwable $exception) {
            // Preserve a pre-send session exception, not a cache-release exception.
            $this->release($lock);
            throw $exception;
        }

        // Callback may already have changed game state. A release failure must not escape.
        return $this->release($lock) ? $result : $unknown;
    }

    private function release(Lock $lock): bool
    {
        try {
            return $lock->release();
        } catch (Throwable) {
            return false;
        }
    }
}
