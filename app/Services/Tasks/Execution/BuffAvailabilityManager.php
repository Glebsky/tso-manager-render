<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Exceptions\BuffUnavailableException;
use App\Exceptions\GameServerErrorException;
use App\Exceptions\SessionBusyException;
use App\Exceptions\SessionExpiredException;
use App\Models\Account;
use App\Services\Game\Zone\HomeZoneRepository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;

class BuffAvailabilityManager
{
    /** Session errors are recovered by the caller's GameSessionGuard, never swallowed here. */
    private const array SESSION_ERROR_CODES = [1005, 1012];

    /** Name fields of a star-menu stack read by findAndDeduct() and isNameMatch(). */
    private const array STATE_BUFF_NAME_KEYS = ['buffName_string', 'name_string', 'buff_raw_name', 'name', 'resourceName_string'];

    public function __construct(
        private readonly HomeZoneRepository $homeZones,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Check if the requested buff is available in star menu, fallback to same buff name if needed,
     * decrement its local counter, and return the resolved unique IDs.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws BuffUnavailableException
     */
    public function checkAndAcquire(Account $account, array $payload): BuffAcquisitionResult
    {
        $targetU1 = isset($payload['unique_id1']) ? (int) $payload['unique_id1'] : 0;
        $targetU2 = isset($payload['unique_id2']) ? (int) $payload['unique_id2'] : 0;
        $targetRawName = (string) ($payload['buff_raw_name'] ?? '');
        $targetBuffName = (string) ($payload['buff_name'] ?? '');
        $targetResName = (string) ($payload['buff_resource_name'] ?? '');
        $requiredAmount = max(1, (int) ($payload['amount'] ?? 1));

        $sessionKey = $this->sessionKey($account, $payload);
        $state = $this->loadSessionState($account, $sessionKey);

        $buffKey = $this->buildBuffKey($targetRawName, $targetBuffName, $targetU1, $targetU2);

        // 1. Fast check: is this buff already known to be depleted in this session?
        if (! empty($state['depleted'][$buffKey]) || (! empty($state['depleted']["{$targetU1}_{$targetU2}"]) && $targetRawName === '')) {
            throw new BuffUnavailableException($targetBuffName ?: $targetRawName, $targetU1, $targetU2);
        }

        // 2. Try to find and deduct from local inventory state
        $match = $this->findAndDeduct($state['buffs'], $targetU1, $targetU2, $targetRawName, $targetBuffName, $targetResName, $requiredAmount);

        if ($match !== null) {
            $state['buffs'] = $match['buffs'];
            $this->saveSessionState($sessionKey, $state);

            return new BuffAcquisitionResult(
                uniqueId1: $match['unique_id1'],
                uniqueId2: $match['unique_id2'],
                buffName: $match['buff_name'] ?: ($targetBuffName ?: $targetRawName),
            );
        }

        // 3. Not found locally. Can we refresh from the game server (max 1 refresh per session)?
        if (! $state['refreshed_from_server']) {
            Log::info("[BuffAvailabilityManager] Buff '{$targetRawName}'/'{$targetBuffName}' (UID {$targetU1}_{$targetU2}) not found locally. Performing 1 server refresh for account #{$account->id}");
            $state['refreshed_from_server'] = true;

            $refreshedBuffs = $this->refreshZoneFromServer($account);
            if ($refreshedBuffs !== null) {
                $state['buffs'] = $refreshedBuffs;
            }

            // Retry search after refresh
            $match = $this->findAndDeduct($state['buffs'], $targetU1, $targetU2, $targetRawName, $targetBuffName, $targetResName, $requiredAmount);
            if ($match !== null) {
                $state['buffs'] = $match['buffs'];
                $this->saveSessionState($sessionKey, $state);

                return new BuffAcquisitionResult(
                    uniqueId1: $match['unique_id1'],
                    uniqueId2: $match['unique_id2'],
                    buffName: $match['buff_name'] ?: ($targetBuffName ?: $targetRawName),
                );
            }
        }

        // 4. Still not found: mark as depleted for this run so subsequent actions skip immediately
        $state['depleted'][$buffKey] = true;
        if ($targetU1 > 0 || $targetU2 > 0) {
            $state['depleted']["{$targetU1}_{$targetU2}"] = true;
        }
        $this->saveSessionState($sessionKey, $state);

        throw new BuffUnavailableException($targetBuffName ?: $targetRawName, $targetU1, $targetU2);
    }

    /**
     * Refund a previously acquired buff back to the session state (e.g. when building validation fails).
     *
     * @param  array<string, mixed>  $payload
     */
    public function release(Account $account, array $payload, BuffAcquisitionResult $buff): void
    {
        $sessionKey = $this->sessionKey($account, $payload);
        $state = $this->loadSessionState($account, $sessionKey);
        $amount = max(1, (int) ($payload['amount'] ?? 1));

        $restored = false;
        foreach ($state['buffs'] as $idx => $item) {
            $u1 = (int) ($item['uniqueId1'] ?? $item['uniqueID1'] ?? $item['uniqueID']['uniqueID1'] ?? $item['uniqueID']['uniqueId1'] ?? $item['uniqueId']['uniqueId1'] ?? 0);
            $u2 = (int) ($item['uniqueId2'] ?? $item['uniqueID2'] ?? $item['uniqueID']['uniqueID2'] ?? $item['uniqueID']['uniqueId2'] ?? $item['uniqueId']['uniqueId2'] ?? 0);

            if ($u1 === $buff->uniqueId1 && $u2 === $buff->uniqueId2) {
                $state['buffs'][$idx]['amount'] = ((int) ($item['amount'] ?? 0)) + $amount;
                $restored = true;
                break;
            }
        }

        if (! $restored) {
            $state['buffs'][] = [
                'uniqueId1' => $buff->uniqueId1,
                'uniqueId2' => $buff->uniqueId2,
                'amount' => $amount,
                'buffName_string' => $buff->buffName,
            ];
        }

        $this->saveSessionState($sessionKey, $state);
    }

    /**
     * Clear cached session state (e.g. for testing or when task finishes).
     */
    public function clearSession(Account $account, ?int $taskId = null, ?string $executionToken = null): void
    {
        $payload = [];
        if ($taskId !== null) {
            $payload['task_id'] = $taskId;
        }
        if ($executionToken !== null) {
            $payload['execution_token'] = $executionToken;
        }
        $this->cache->forget($this->sessionKey($account, $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sessionKey(Account $account, array $payload): string
    {
        $taskId = $payload['task_id'] ?? $payload['_task_id'] ?? null;
        $token = $payload['execution_token'] ?? $payload['_execution_token'] ?? null;

        if ($taskId !== null) {
            $suffix = $token !== null ? (string) $token : 'run';

            return "buff_avail:{$account->id}:{$taskId}:{$suffix}";
        }

        return "buff_avail:{$account->id}:default";
    }

    /**
     * @return array{buffs: list<array<string, mixed>>, depleted: array<string, bool>, refreshed_from_server: bool}
     */
    private function loadSessionState(Account $account, string $sessionKey): array
    {
        $cached = $this->cache->get($sessionKey);
        if (is_array($cached) && isset($cached['buffs'], $cached['depleted']) && is_array($cached['buffs']) && is_array($cached['depleted'])) {
            /** @var array{buffs: list<array<string, mixed>>, depleted: array<string, bool>, refreshed_from_server: bool} $cached */
            return $cached;
        }

        $zoneData = is_array($account->zone_data) ? $account->zone_data : [];
        $buffs = $zoneData['availableBuffs'] ?? $zoneData['buffs'] ?? [];

        return [
            'buffs' => is_array($buffs) ? array_values($buffs) : [],
            'depleted' => [],
            'refreshed_from_server' => false,
        ];
    }

    /**
     * @param  array{buffs: list<array<string, mixed>>, depleted: array<string, bool>, refreshed_from_server: bool}  $state
     */
    private function saveSessionState(string $sessionKey, array $state): void
    {
        // Saved after every buff step to remote Redis: keep only what is read back.
        $state['buffs'] = $this->compactBuffs($state['buffs']);
        $this->cache->put($sessionKey, $state, 1800);
    }

    /**
     * The raw star-menu stacks carry the whole AMF object; findAndDeduct(),
     * isNameMatch() and release() only read the ids, amount and names.
     *
     * @param  array<mixed>  $buffs  untrusted: read back from the cache
     * @return list<array<string, mixed>>
     */
    private function compactBuffs(array $buffs): array
    {
        $compact = [];
        foreach ($buffs as $buff) {
            if (! is_array($buff)) {
                continue;
            }

            $item = [
                'uniqueId1' => (int) ($buff['uniqueId1'] ?? $buff['uniqueID1'] ?? $buff['uniqueID']['uniqueID1'] ?? $buff['uniqueID']['uniqueId1'] ?? $buff['uniqueId']['uniqueId1'] ?? 0),
                'uniqueId2' => (int) ($buff['uniqueId2'] ?? $buff['uniqueID2'] ?? $buff['uniqueID']['uniqueID2'] ?? $buff['uniqueID']['uniqueId2'] ?? $buff['uniqueId']['uniqueId2'] ?? 0),
                'amount' => (int) ($buff['amount'] ?? 0),
            ];
            foreach (self::STATE_BUFF_NAME_KEYS as $key) {
                if (isset($buff[$key]) && is_scalar($buff[$key])) {
                    $item[$key] = $buff[$key];
                }
            }
            $compact[] = $item;
        }

        return $compact;
    }

    /**
     * @param  list<array<string, mixed>>  $buffs
     * @return array{buffs: list<array<string, mixed>>, unique_id1: int, unique_id2: int, buff_name: string}|null
     */
    private function findAndDeduct(
        array $buffs,
        int $targetU1,
        int $targetU2,
        string $targetRawName,
        string $targetBuffName,
        string $targetResName,
        int $requiredAmount
    ): ?array {
        // Pass 1: exact unique IDs match
        if ($targetU1 > 0 || $targetU2 > 0) {
            foreach ($buffs as $idx => $buff) {
                $u1 = (int) ($buff['uniqueId1'] ?? $buff['uniqueID1'] ?? $buff['uniqueID']['uniqueID1'] ?? $buff['uniqueID']['uniqueId1'] ?? $buff['uniqueId']['uniqueId1'] ?? 0);
                $u2 = (int) ($buff['uniqueId2'] ?? $buff['uniqueID2'] ?? $buff['uniqueID']['uniqueID2'] ?? $buff['uniqueID']['uniqueId2'] ?? $buff['uniqueId']['uniqueId2'] ?? 0);

                if ($u1 === $targetU1 && $u2 === $targetU2) {
                    $amount = (int) ($buff['amount'] ?? 0);
                    if ($amount >= $requiredAmount) {
                        $buffs[$idx]['amount'] = $amount - $requiredAmount;
                        $rawName = (string) ($buff['buffName_string'] ?? $buff['name_string'] ?? $buff['buff_raw_name'] ?? $buff['name'] ?? $targetBuffName);

                        return [
                            'buffs' => $buffs,
                            'unique_id1' => $u1,
                            'unique_id2' => $u2,
                            'buff_name' => $rawName,
                        ];
                    }
                }
            }
        }

        // Pass 2: fallback to any other stack of the same buff type
        if ($targetRawName !== '' || $targetBuffName !== '' || $targetResName !== '') {
            foreach ($buffs as $idx => $buff) {
                if ($this->isNameMatch($buff, $targetRawName, $targetBuffName, $targetResName)) {
                    $amount = (int) ($buff['amount'] ?? 0);
                    if ($amount >= $requiredAmount) {
                        $u1 = (int) ($buff['uniqueId1'] ?? $buff['uniqueID1'] ?? $buff['uniqueID']['uniqueID1'] ?? $buff['uniqueID']['uniqueId1'] ?? $buff['uniqueId']['uniqueId1'] ?? 0);
                        $u2 = (int) ($buff['uniqueId2'] ?? $buff['uniqueID2'] ?? $buff['uniqueID']['uniqueID2'] ?? $buff['uniqueID']['uniqueId2'] ?? $buff['uniqueId']['uniqueId2'] ?? 0);

                        $buffs[$idx]['amount'] = $amount - $requiredAmount;
                        $rawName = (string) ($buff['buffName_string'] ?? $buff['name_string'] ?? $buff['buff_raw_name'] ?? $buff['name'] ?? $targetBuffName);

                        return [
                            'buffs' => $buffs,
                            'unique_id1' => $u1,
                            'unique_id2' => $u2,
                            'buff_name' => $rawName ?: ($targetBuffName ?: $targetRawName),
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $buff
     */
    private function isNameMatch(array $buff, string $rawNameTarget, string $buffNameTarget, string $resNameTarget): bool
    {
        $stackRaw = (string) ($buff['buffName_string'] ?? $buff['buff_raw_name'] ?? $buff['name_string'] ?? $buff['name'] ?? '');
        $stackRes = (string) ($buff['resourceName_string'] ?? '');

        $targets = array_filter([$rawNameTarget, $buffNameTarget, $resNameTarget], static fn (string $s): bool => $s !== '');

        foreach ($targets as $target) {
            if ($stackRaw !== '' && strcasecmp($stackRaw, $target) === 0) {
                return true;
            }
            if ($stackRes !== '' && strcasecmp($stackRes, $target) === 0) {
                return true;
            }
        }

        return false;
    }

    private function buildBuffKey(string $rawName, string $buffName, int $u1, int $u2): string
    {
        if ($rawName !== '') {
            return strtolower($rawName);
        }
        if ($buffName !== '') {
            return strtolower($buffName);
        }

        return "{$u1}_{$u2}";
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function refreshZoneFromServer(Account $account): ?array
    {
        try {
            $zoneData = $this->homeZones->refresh($account);

            $buffs = $zoneData['availableBuffs'] ?? $zoneData['buffs'] ?? [];

            return is_array($buffs) ? array_values($buffs) : [];
        } catch (SessionBusyException|SessionExpiredException $e) {
            // Session problems belong to the action's GameSessionGuard; a
            // swallowed 1012 here used to be reported as "buff unavailable".
            throw $e;
        } catch (GameServerErrorException $e) {
            if (in_array((int) $e->getCode(), self::SESSION_ERROR_CODES, true)) {
                throw $e;
            }
            Log::warning("[BuffAvailabilityManager] Server returned errorCode {$e->getCode()} while refreshing zone for account #{$account->id}");

            return null;
        } catch (\Throwable $e) {
            Log::warning("[BuffAvailabilityManager] Failed to refresh zone from server for account #{$account->id}: {$e->getMessage()}");

            return null;
        }
    }
}
