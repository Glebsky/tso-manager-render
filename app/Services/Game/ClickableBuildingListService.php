<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Models\Account;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClickableBuildingListService
{
    public const int CACHE_TTL_SECONDS = 30;

    /**
     * Known quest gift building name patterns (candidates).
     *
     * @var list<string>
     */
    private const array QUEST_GIFT_CANDIDATE_PATTERNS = [
        '/^FlyingHouse.*$/i',
        '/^BalloonMarket.*$/i',
        '/^Christmas.*$/i',
        '/^GiftGhostShip.*$/i',
        '/^GhostLantern.*$/i',
        '/^LoveTree.*$/i',
        '/^Snowglobe.*$/i',
        '/^EW_Balloons_.*$/i',
    ];

    public function __construct(
        private readonly ClickableBuildingRegistry $registry,
        private readonly QuestTriggerBuildingProvider $questProvider,
        private readonly ZoneParserService $zoneParser,
        private readonly TsoAmfService $amf,
    ) {}

    /**
     * @return list<ClickableBuildingDto>
     */
    public function forAccount(Account $account, bool $skipCache = false): array
    {
        $cacheKey = self::cacheKey($account->id);

        if (! $skipCache && Cache::has($cacheKey)) {
            /** @var list<array{grid: int, building_name: string, kind: string, available: bool|null}> $cached */
            $cached = Cache::get($cacheKey);

            return array_map(
                static fn (array $item) => new ClickableBuildingDto(
                    grid: (int) $item['grid'],
                    buildingName: (string) $item['building_name'],
                    kind: (string) $item['kind'],
                    available: isset($item['available']) ? (bool) $item['available'] : null
                ),
                $cached
            );
        }

        $dtos = $this->buildList($account);

        Cache::put(
            $cacheKey,
            array_map(static fn (ClickableBuildingDto $dto) => $dto->toArray(), $dtos),
            self::CACHE_TTL_SECONDS
        );

        return $dtos;
    }

    public static function cacheKey(int $accountId): string
    {
        return "clickable_buildings_$accountId";
    }

    public static function clearCache(int $accountId): void
    {
        Cache::forget(self::cacheKey($accountId));
    }

    /**
     * @return list<ClickableBuildingDto>
     */
    private function buildList(Account $account): array
    {
        $buildings = $this->getBuildings($account);
        $activeQuestBuildings = $this->questProvider->forAccount($account);

        $result = [];
        foreach ($buildings as $building) {
            $grid = (int) ($building['buildingGrid'] ?? $building['grid'] ?? 0);
            if ($grid <= 0) {
                continue;
            }

            $name = (string) ($building['buildingName'] ?? $building['name'] ?? $building['buildingName_string'] ?? '');
            if ($name === '') {
                continue;
            }

            $result[] = $this->classifyBuilding($grid, $name, $activeQuestBuildings);
        }

        return $result;
    }

    /**
     * @param  list<string>|null  $activeQuestBuildings
     */
    public function classifyBuilding(int $grid, string $buildingName, ?array $activeQuestBuildings): ClickableBuildingDto
    {
        // 1. Collectible allow-list match
        if ($this->registry->isClickable($buildingName)) {
            return new ClickableBuildingDto(
                grid: $grid,
                buildingName: $buildingName,
                kind: 'collectible',
                available: true
            );
        }

        // 2. Active quest trigger match
        if ($activeQuestBuildings !== null && in_array($buildingName, $activeQuestBuildings, true)) {
            return new ClickableBuildingDto(
                grid: $grid,
                buildingName: $buildingName,
                kind: 'quest_gift',
                available: true
            );
        }

        // 3. Known quest gift candidate check
        if ($this->isKnownQuestGiftCandidate($buildingName)) {
            return new ClickableBuildingDto(
                grid: $grid,
                buildingName: $buildingName,
                kind: 'quest_gift',
                available: $activeQuestBuildings === null ? null : false
            );
        }

        // 4. Regular production or other building
        return new ClickableBuildingDto(
            grid: $grid,
            buildingName: $buildingName,
            kind: 'none',
            available: null
        );
    }

    public function isKnownQuestGiftCandidate(string $buildingName): bool
    {
        return array_any(
            self::QUEST_GIFT_CANDIDATE_PATTERNS,
            static fn (string $pattern): bool => preg_match($pattern, $buildingName) === 1
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getBuildings(Account $account): array
    {
        try {
            $zoneData = $account->zone_data;
            if (is_array($zoneData) && isset($zoneData['buildings']) && is_array($zoneData['buildings'])) {
                /** @var list<array<string, mixed>> $bList */
                $bList = $zoneData['buildings'];

                return $bList;
            }

            $rawAmf = $this->amf->getZone($account);
            $parsed = $this->zoneParser->parse($rawAmf);

            /** @var list<array<string, mixed>> $bList */
            $bList = (array) ($parsed['buildings'] ?? []);

            return $bList;
        } catch (Throwable $e) {
            Log::warning("[ClickableBuildingListService] Account #$account->id: failed to load buildings: {$e->getMessage()}");

            return [];
        }
    }
}
