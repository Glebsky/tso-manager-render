<?php

declare(strict_types=1);

namespace App\Services\Tasks\Handlers;

use App\Enums\BuildingClickMode;
use App\Enums\TaskType;
use App\Exceptions\BuildingNotClickableException;
use App\Exceptions\GameServerErrorException;
use App\Exceptions\InvalidTaskTypeException;
use App\Models\Account;
use App\Services\Game\BuildingClickResolver;
use App\Services\Game\ClickableBuildingListService;
use App\Services\GameErrorResolver;
use App\Services\Tasks\Contracts\TaskActionHandlerInterface;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;
use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class CollectBuildingHandler implements TaskActionHandlerInterface
{
    /** Session errors that must bubble up so TaskExecutionService can re-login and retry. */
    private const array SESSION_ERROR_CODES = [1005, 1012];

    public function __construct(
        private ZoneParserService $zones,
        private TsoAmfService $amf,
        private BuildingClickResolver $resolver,
    ) {}

    public function supports(string $actionType): bool
    {
        return $actionType === TaskType::CollectBuilding->value;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws BuildingNotClickableException
     * @throws GameServerErrorException
     * @throws InvalidTaskTypeException
     * @throws Exception
     */
    public function handle(Account $account, array $payload): string
    {
        $grid = (int) ($payload['grid'] ?? 0);
        if ($grid <= 0) {
            throw new InvalidTaskTypeException('Invalid building grid parameter.', 422);
        }

        $modeRaw = (string) ($payload['mode'] ?? 'auto');
        $requestedMode = BuildingClickMode::tryFrom($modeRaw) ?? BuildingClickMode::Auto;

        $zoneAmf = $this->amf->getZone($account);
        $zone = $this->zones->parse($zoneAmf);

        $errorCode = (int) ($zone['errorCode'] ?? 0);
        if ($errorCode !== 0) {
            throw new GameServerErrorException($errorCode, GameErrorResolver::getMessage($errorCode));
        }

        $buildings = (array) ($zone['buildings'] ?? []);
        $targetBuilding = null;

        foreach ($buildings as $b) {
            if (! is_array($b)) {
                continue;
            }

            $bGrid = (int) ($b['buildingGrid'] ?? $b['grid'] ?? 0);
            if ($bGrid === $grid) {
                $targetBuilding = $b;
                break;
            }
        }

        if ($targetBuilding === null) {
            Log::info("[CollectBuilding] Account #{$account->id}: no building found at grid {$grid}, skipped");

            return __('tasks.building_collect.not_found', ['grid' => $grid]);
        }

        $buildingName = (string) ($targetBuilding['buildingName'] ?? $targetBuilding['name'] ?? $targetBuilding['buildingName_string'] ?? $payload['building_name'] ?? '');

        $decision = $this->resolver->resolve($requestedMode, $grid, $buildingName);

        Log::info(sprintf(
            '[CollectBuilding] Account #%d: grid=%d, building="%s", mode=%s (requested=%s), reason=%s',
            $account->id,
            $grid,
            $buildingName,
            $decision->mode->value,
            $requestedMode->value,
            $decision->reason
        ));

        $rawAmf = match ($decision->mode) {
            BuildingClickMode::Collectible => $this->amf->collectCollectible($account, $grid),
            BuildingClickMode::QuestTrigger, BuildingClickMode::Auto => $this->amf->sendBuildingSelectedQuestTrigger($account, $grid),
        };

        $responseCode = $this->extractErrorCode($rawAmf);

        if ($responseCode === 0) {
            ClickableBuildingListService::clearCache($account->id);

            return $decision->mode === BuildingClickMode::Collectible
                ? __('tasks.building_collect.collected', ['name' => $buildingName, 'grid' => $grid])
                : __('tasks.building_collect.gift_received', ['name' => $buildingName, 'grid' => $grid]);
        }

        if (in_array($responseCode, self::SESSION_ERROR_CODES, true)) {
            throw new GameServerErrorException($responseCode, GameErrorResolver::getMessage($responseCode));
        }

        if ($responseCode === 551) {
            Log::warning("[CollectBuilding] Account #{$account->id}: building {$buildingName} at grid {$grid} returned code 551 (nothing to collect)");

            return __('tasks.building_collect.nothing_to_collect', ['name' => $buildingName, 'grid' => $grid]);
        }

        Log::warning("[CollectBuilding] Account #{$account->id}: building {$buildingName} at grid {$grid} skipped with game error code {$responseCode}");

        return GameErrorResolver::getMessage($responseCode);
    }

    private function extractErrorCode(string $rawAmf): int
    {
        try {
            $parsed = $this->zones->parse($rawAmf);

            return (int) ($parsed['errorCode'] ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }
}
