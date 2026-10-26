<?php

declare(strict_types=1);

namespace App\Services\Game\Session;

use App\Exceptions\SessionBusyException;
use App\Models\Account;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/** Shared identity of one running game client, independent of web authentication. */
final class GameClientIdentity
{
    public function current(Account $account): int
    {
        $key = $this->key($account);
        $value = Cache::get($key);
        if ($value === null) {
            // Atomic add: simultaneous first requests must share one identity.
            Cache::add($key, random_int(1, 2147483646), now()->addDays(30));
            $value = Cache::get($key);
        }
        if ((! is_int($value) && ! (is_string($value) && ctype_digit($value))) || (int) $value < 0 || (int) $value > 2147483646) {
            throw new RuntimeException('Invalid cached game client identity');
        }

        return (int) $value;
    }

    /**
     * Renew only the rejected identity. A parallel caller may already have won.
     * Uses the existing AMF mutex; never call while holding that mutex.
     *
     * @throws SessionBusyException
     */
    public function renew(Account $account, int $rejectedIdentity): bool
    {
        try {
            return Cache::lock('tso:amf_lock:'.$account->id, (int) config('game.session_lock_ttl_seconds', 130))
                ->block((int) config('game.session_lock_wait_seconds', 20), function () use ($account, $rejectedIdentity): bool {
                    if ($this->current($account) !== $rejectedIdentity) {
                        return false;
                    }
                    do {
                        $next = random_int(1, 2147483646);
                    } while ($next === $rejectedIdentity);
                    Cache::put($this->key($account), $next, now()->addDays(30));

                    return true;
                });
        } catch (LockTimeoutException) {
            if ($this->current($account) !== $rejectedIdentity) {
                return false;
            }
            throw SessionBusyException::forAccount($account->id, 'identity recovery', (int) config('game.session_lock_wait_seconds', 20));
        }
    }

    public function forget(Account|int $account): void
    {
        $id = $account instanceof Account ? $account->id : $account;
        Cache::forget('tso:client_id:'.$id);
    }

    private function key(Account $account): string
    {
        return 'tso:client_id:'.$account->id;
    }
}
