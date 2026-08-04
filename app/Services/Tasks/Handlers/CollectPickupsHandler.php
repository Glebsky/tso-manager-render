<?php

declare(strict_types=1);

namespace App\Services\Tasks\Handlers;

use App\Exceptions\GameServerErrorException;
use App\Exceptions\PickupsUnavailableException;
use App\Models\Account;
use App\Services\GameErrorResolver;
use App\Services\Tasks\Contracts\TaskActionHandlerInterface;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Clicks every available collectible (pickup) on the account's own island.
 *
 * A "click" on a collection is nothing but COMMAND.EXECUTE_PICKUP (13002)
 * with a bare dUniqueID payload — see PickupService.executePickup() in the
 * game client. The list of currently spawned collectibles lives in
 * dZoneVO.pickups, so the zone is always re-read right before collecting
 * (cached zone_data would contain stale/consumed uids).
 */
final class CollectPickupsHandler implements TaskActionHandlerInterface
{
    /** CollectionsConsts.COLLECTIBLE_BUILDING_NORMAL */
    private const TYPE_NORMAL = 0;

    /** CollectionsConsts.COLLECTIBLE_BUILDING_EVENT */
    private const TYPE_EVENT = 1;

    private const DEFAULT_DELAY_MS = 250;

    private const MAX_DELAY_MS = 5000;

    /** Session errors that must bubble up so TaskExecutionService can re-login and retry. */
    private const SESSION_ERROR_CODES = [1005, 1012];

    public function __construct(
        private readonly TsoAmfService $amfService,
        private readonly ZoneParserService $zoneParser,
    ) {}

    public function supports(string $actionType): bool
    {
        return $actionType === 'collect_pickups';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Account $account, array $payload): string
    {
        $zone = $this->zoneParser->parse($this->amfService->getZone($account));

        $errorCode = (int) ($zone['errorCode'] ?? 0);
        if ($errorCode !== 0) {
            throw new GameServerErrorException($errorCode, GameErrorResolver::getMessage($errorCode));
        }

        if (! array_key_exists('pickups', $zone) || ! is_array($zone['pickups'])) {
            throw new PickupsUnavailableException;
        }

        $pickups = $this->filter($this->normalize($zone['pickups']), $payload);

        if ($pickups === []) {
            return __('tasks.pickups.none_available');
        }

        $delayMs = max(0, min(self::MAX_DELAY_MS, (int) ($payload['delay_ms'] ?? self::DEFAULT_DELAY_MS)));

        $collected = 0;
        $skipped = 0;
        /** @var array<string, int> $skipReasons */
        $skipReasons = [];
        $total = count($pickups);

        foreach ($pickups as $index => $pickup) {
            try {
                $raw = $this->amfService->executePickup($account, $pickup['unique_id1'], $pickup['unique_id2']);
                $code = $this->extractErrorCode($raw);

                if ($code === 0) {
                    $collected++;
                } elseif (in_array($code, self::SESSION_ERROR_CODES, true)) {
                    throw new GameServerErrorException($code, GameErrorResolver::getMessage($code));
                } else {
                    // Stale uid ("unable to find resource pickup to execute") or a full
                    // collectible storage — a skip, never a failure of the whole task.
                    $skipped++;
                    $skipReasons['code '.$code] = ($skipReasons['code '.$code] ?? 0) + 1;
                    Log::warning("[CollectPickups] Account #{$account->id}: pickup {$pickup['unique_id1']}_{$pickup['unique_id2']} skipped with game error {$code}");
                }
            } catch (GameServerErrorException $e) {
                throw $e;
            } catch (Throwable $e) {
                $skipped++;
                $skipReasons['error'] = ($skipReasons['error'] ?? 0) + 1;
                Log::warning("[CollectPickups] Account #{$account->id}: pickup {$pickup['unique_id1']}_{$pickup['unique_id2']} failed: ".$e->getMessage());
            }

            if ($delayMs > 0 && $index < $total - 1) {
                usleep($delayMs * 1000);
            }
        }

        $summary = __('tasks.pickups.summary', ['collected' => $collected, 'total' => $total]);

        if ($skipped > 0) {
            $details = [];
            foreach ($skipReasons as $reason => $count) {
                $details[] = $count.'x '.$reason;
            }
            $summary .= ', '.__('tasks.pickups.skipped', ['skipped' => $skipped, 'details' => implode(', ', $details)]);
        }

        return $summary;
    }

    /**
     * Accepts both snake_case (parse_zone.py output) and raw AMF spelling.
     *
     * @param  array<int, mixed>  $rawPickups
     * @return array<int, array{unique_id1: int, unique_id2: int, type: int, resource: string, grid: int}>
     */
    private function normalize(array $rawPickups): array
    {
        $normalized = [];

        foreach ($rawPickups as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $uid = is_array($raw['uniqueID'] ?? null) ? $raw['uniqueID'] : (is_array($raw['uniqueId'] ?? null) ? $raw['uniqueId'] : []);

            $uid1 = (int) ($raw['unique_id1'] ?? $raw['uniqueID1'] ?? $raw['uniqueId1'] ?? $uid['uniqueID1'] ?? $uid['uniqueId1'] ?? 0);
            $uid2 = (int) ($raw['unique_id2'] ?? $raw['uniqueID2'] ?? $raw['uniqueId2'] ?? $uid['uniqueID2'] ?? $uid['uniqueId2'] ?? 0);

            if ($uid1 === 0 && $uid2 === 0) {
                continue;
            }

            $normalized[] = [
                'unique_id1' => $uid1,
                'unique_id2' => $uid2,
                'type' => (int) ($raw['type'] ?? $raw['providerType'] ?? self::TYPE_NORMAL),
                'resource' => (string) ($raw['resource'] ?? $raw['resourceName_string'] ?? $raw['item_string'] ?? ''),
                'grid' => (int) ($raw['grid'] ?? 0),
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{unique_id1: int, unique_id2: int, type: int, resource: string, grid: int}>  $pickups
     * @param  array<string, mixed>  $payload
     * @return array<int, array{unique_id1: int, unique_id2: int, type: int, resource: string, grid: int}>
     */
    private function filter(array $pickups, array $payload): array
    {
        $pickupType = (string) ($payload['pickup_type'] ?? 'all');

        if ($pickupType === 'normal' || $pickupType === 'event') {
            $wanted = $pickupType === 'event' ? self::TYPE_EVENT : self::TYPE_NORMAL;
            $pickups = array_values(array_filter(
                $pickups,
                static fn (array $pickup): bool => $pickup['type'] === $wanted
            ));
        }

        $resources = $payload['resources'] ?? null;
        if (is_array($resources) && $resources !== []) {
            $allowed = array_map(
                static fn ($resource): string => mb_strtolower(trim((string) $resource)),
                $resources
            );
            $pickups = array_values(array_filter(
                $pickups,
                static fn (array $pickup): bool => in_array(mb_strtolower($pickup['resource']), $allowed, true)
            ));
        }

        $limit = (int) ($payload['limit'] ?? 0);
        if ($limit > 0) {
            $pickups = array_slice($pickups, 0, $limit);
        }

        return $pickups;
    }

    /**
     * Server replies with a game tick command; a non-zero errorCode means the
     * pickup could not be executed. Unparsable responses count as success,
     * mirroring TaskExecutionService::executeSingleAction().
     */
    private function extractErrorCode(string $rawAmf): int
    {
        try {
            $parsed = $this->zoneParser->parse($rawAmf);

            return (int) ($parsed['errorCode'] ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }
}
