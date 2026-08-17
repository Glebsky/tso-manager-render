<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Models\Account;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;
use Illuminate\Support\Facades\Log;
use Throwable;

class QuestTriggerBuildingProvider
{
    private const int TYPE_BUILDING = 1;

    private const int CONDITION_SELECTED = 2;

    private const string ACTION_BUILDING_SELECTED = 'buildingselected';

    private const int QUEST_MODE_DEACTIVATED = 5;

    public function __construct(
        private TsoAmfService $amf,
        private ZoneParserService $parser,
    ) {}

    /**
     * Names of buildings with active buildingselected trigger.
     * null — quest pool unavailable / error (NOT empty array!).
     *
     * @return list<string>|null
     */
    public function forAccount(Account $account): ?array
    {
        try {
            $rawAmf = $this->amf->getLatestQuestList($account);
            $parsed = $this->parser->parse($rawAmf);

            if ((int) ($parsed['errorCode'] ?? 0) !== 0) {
                Log::warning("[QuestTriggerBuildingProvider] Account #{$account->id}: quest list returned errorCode {$parsed['errorCode']}");

                return null;
            }

            return $this->extractBuildingNames($parsed);
        } catch (Throwable $e) {
            Log::warning("[QuestTriggerBuildingProvider] Account #{$account->id}: failed to fetch quest pool: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public function extractBuildingNames(array $data): array
    {
        $names = [];

        // Support both direct dQuestPoolVO key and root list
        $questPool = $data['dQuestPoolVO'] ?? $data['questPool'] ?? $data;
        $quests = $questPool['mQuestVO_vector'] ?? $questPool['quests'] ?? $data['mQuestVO_vector'] ?? [];

        if (! is_array($quests)) {
            return [];
        }

        foreach ($quests as $quest) {
            if (! is_array($quest)) {
                continue;
            }

            $questMode = (int) ($quest['mQuestMode'] ?? $quest['questMode'] ?? 0);
            if ($questMode >= self::QUEST_MODE_DEACTIVATED) {
                continue;
            }

            $definition = $quest['mQuestDefinition'] ?? $quest['questDefinition'] ?? $quest;
            if (! is_array($definition)) {
                continue;
            }

            $triggers = $definition['questTriggers_vector'] ?? $definition['triggers'] ?? [];
            if (! is_array($triggers)) {
                continue;
            }

            foreach ($triggers as $trigger) {
                if (! is_array($trigger)) {
                    continue;
                }

                $type = (int) ($trigger['type'] ?? 0);
                $condition = (int) ($trigger['condition'] ?? 0);
                $actionName = (string) ($trigger['actionName_string'] ?? $trigger['actionName'] ?? $trigger['actionType_string'] ?? '');

                $isBuilding = ($type === self::TYPE_BUILDING);
                $isSelected = ($condition === self::CONDITION_SELECTED || $condition === 0 || strcasecmp((string) $condition, 'selected') === 0);
                $isActionMatch = (strcasecmp($actionName, self::ACTION_BUILDING_SELECTED) === 0 || empty($actionName));

                if ($isBuilding && ($isSelected || $isActionMatch)) {
                    $bName = (string) ($trigger['name_string'] ?? $trigger['name'] ?? '');
                    if ($bName !== '') {
                        $names[] = $bName;
                    }
                }
            }
        }

        return array_values(array_unique($names));
    }
}
