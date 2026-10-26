<?php

declare(strict_types=1);

namespace App\Services\Game\Quests;

final readonly class DailyLoginQuestSelector
{
    /**
     * null = missing/malformed pool; [] = an explicitly empty pool.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>|null
     */
    public function quests(array $data): ?array
    {
        $pool = $data['questPool'] ?? $data['dQuestPoolVO'] ?? null;
        if (! is_array($pool) || ! isset($pool['mQuestVO_vector']) || ! is_array($pool['mQuestVO_vector'])) {
            return null;
        }
        $quests = $pool['mQuestVO_vector'];
        if (! array_is_list($quests)) {
            return null;
        }
        foreach ($quests as $quest) {
            if (! is_array($quest) || ! isset($quest['mQuestMode']) || ! is_int($quest['mQuestMode'])) {
                return null;
            }
        }

        return $quests;
    }

    /** @param array<string, mixed> $quest
     * @return array{int,int}|null
     */
    public function identity(array $quest): ?array
    {
        $uid = $quest['mUniqueID'] ?? $quest['uniqueId'] ?? $quest['uniqueID'] ?? null;
        if (! is_array($uid)) {
            return null;
        }
        $a = $uid['uniqueID1'] ?? null;
        $b = $uid['uniqueID2'] ?? null;
        foreach ([$a, $b] as $part) {
            if (! is_int($part) || $part < -2147483648 || $part > 2147483647) {
                return null;
            }
        }

        return ($a === 0 && $b === 0) ? null : [$a, $b];
    }

    /** @param array<string, mixed> $quest */
    public function isClaimable(array $quest): bool
    {
        if (($quest['mQuestMode'] ?? null) !== 5 || $this->identity($quest) === null) {
            return false;
        }
        $definition = $quest['mQuestDefinition'] ?? null;
        if (! is_array($definition) || ($definition['showRewardWindow'] ?? false) !== true) {
            return false;
        }
        $triggers = $definition['questTriggers_vector'] ?? null;
        if (! is_array($triggers)) {
            return false;
        }
        foreach ($triggers as $trigger) {
            if (is_array($trigger) && ($trigger['type'] ?? null) === 13) {
                return true;
            }
        }

        return false;
    }
}
