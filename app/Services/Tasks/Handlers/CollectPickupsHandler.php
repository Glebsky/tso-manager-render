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
use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Clicks every available collectible (pickup) on the account's own island.
 *
 * Collectibles are ordinary buildings whose name is registered in
 * collections.xml (CollectionsManager.getBuildingIsCollectible()). A click is
 * cGameInterface.SelectBuilding() -> cCollectibleBuilding.handleSelectBuilding()
 * -> cZone.SendDestructBuildingCommand(building, "cCollectibleBuilding"), i.e.
 * COMMAND.DESTRUCT_BUILDING (65) addressed by the building grid.
 *
 * The zone is always re-read right before collecting, because cached zone_data
 * would list collectibles that have already been consumed or despawned.
 */
final class CollectPickupsHandler implements TaskActionHandlerInterface
{
    /** CollectionsConsts.COLLECTIBLE_BUILDING_NORMAL */
    private const int TYPE_NORMAL = 0;

    /** CollectionsConsts.COLLECTIBLE_BUILDING_EVENT */
    private const int TYPE_EVENT = 1;

    private const int DEFAULT_DELAY_MS = 250;

    private const int MAX_DELAY_MS = 5000;

    /** Session errors that must bubble up so TaskExecutionService can re-login and retry. */
    private const array SESSION_ERROR_CODES = [1005, 1012];

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
     *
     * @throws GameServerErrorException
     * @throws PickupsUnavailableException
     * @throws Exception
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

        $normalized = $this->normalize($zone['pickups']);
        $pickups = $this->filter($normalized, $payload);

        Log::info(sprintf(
            '[CollectPickups] Account #%d: zone reports %d collectible building(s), %d left after filters (pickup_type=%s, resources=%s, limit=%s)',
            $account->id,
            count($normalized),
            count($pickups),
            $payload['pickup_type'] ?? 'all',
            is_array($payload['resources'] ?? null) && $payload['resources'] !== []
                ? implode('|', array_map('strval', $payload['resources']))
                : 'any',
            (int) ($payload['limit'] ?? 0) > 0 ? (string) (int) $payload['limit'] : 'none'
        ));

        if ($pickups === []) {
            if ($normalized !== []) {
                Log::warning("[CollectPickups] Account #{$account->id}: every collectible was filtered out by the task payload");
            }

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
                if ($pickup['grid'] <= 0) {
                    $skipped++;
                    $skipReasons['no grid'] = ($skipReasons['no grid'] ?? 0) + 1;
                    Log::warning("[CollectPickups] Account #{$account->id}: collectible {$pickup['resource']} has no grid, skipped");

                    continue;
                }

                $raw = $this->amfService->collectCollectible($account, $pickup['grid']);
                $code = $this->extractErrorCode($raw);

                if ($code === 0) {
                    $collected++;
                    Log::info(sprintf(
                        '[CollectPickups] Account #%d: collected %s (grid %d, type %d, uid %d_%d)',
                        $account->id,
                        $pickup['resource'] !== '' ? $pickup['resource'] : 'collectible',
                        $pickup['grid'],
                        $pickup['type'],
                        $pickup['unique_id1'],
                        $pickup['unique_id2']
                    ));
                } elseif (in_array($code, self::SESSION_ERROR_CODES, true)) {
                    throw new GameServerErrorException($code, GameErrorResolver::getMessage($code));
                } else {
                    // Already despawned/collected building or a full collectible
                    // storage — a skip, never a failure of the whole task.
                    $skipped++;
                    $skipReasons['code '.$code] = ($skipReasons['code '.$code] ?? 0) + 1;
                    Log::warning("[CollectPickups] Account #{$account->id}: collectible {$pickup['resource']} at grid {$pickup['grid']} skipped with game error {$code}");
                }
            } catch (GameServerErrorException $e) {
                throw $e;
            } catch (Throwable $e) {
                $skipped++;
                $skipReasons['error'] = ($skipReasons['error'] ?? 0) + 1;
                Log::warning("[CollectPickups] Account #{$account->id}: collectible {$pickup['resource']} at grid {$pickup['grid']} failed: ".$e->getMessage());
            }

            if ($delayMs > 0 && $index < $total - 1) {
                usleep($delayMs * 1000);
            }
        }

        $summary = __('tasks.pickups.summary', ['collected' => $collected, 'total' => $total]);

        Log::info("[CollectPickups] Account #{$account->id}: finished, collected {$collected} of {$total}, skipped {$skipped}");

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
            $uid = is_array($raw['uniqueID'] ?? null)
                ? $raw['uniqueID']
                : (array) ($raw['uniqueId'] ?? []);

            $uid1 = (int) ($raw['unique_id1'] ?? $raw['uniqueID1'] ?? $raw['uniqueId1'] ?? $uid['uniqueID1'] ?? $uid['uniqueId1'] ?? 0);
            $uid2 = (int) ($raw['unique_id2'] ?? $raw['uniqueID2'] ?? $raw['uniqueId2'] ?? $uid['uniqueID2'] ?? $uid['uniqueId2'] ?? 0);
            $grid = (int) ($raw['grid'] ?? $raw['buildingGrid'] ?? 0);

            // The grid is what identifies a collectible for DESTRUCT_BUILDING;
            // uids are kept for logging only.
            if ($grid <= 0) {
                continue;
            }

            $normalized[] = [
                'unique_id1' => $uid1,
                'unique_id2' => $uid2,
                'type' => (int) ($raw['type'] ?? $raw['providerType'] ?? self::TYPE_NORMAL),
                'resource' => (string) ($raw['resource'] ?? $raw['building_name'] ?? $raw['buildingName_string'] ?? $raw['resourceName_string'] ?? $raw['item_string'] ?? ''),
                'grid' => $grid,
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
