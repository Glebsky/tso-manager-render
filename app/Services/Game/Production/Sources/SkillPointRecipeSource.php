<?php

declare(strict_types=1);

namespace App\Services\Game\Production\Sources;

use App\Services\Game\Production\Normalizers\CostNormalizer;
use App\Services\Game\Production\Normalizers\InstantFinishCostNormalizer;
use DOMDocument;
use DOMElement;
use DOMNode;

final readonly class SkillPointRecipeSource implements RecipeSourceInterface
{
    private InstantFinishCostNormalizer $instantCostNormalizer;

    private CostNormalizer $costNormalizer;

    /**
     * @param  list<array<string, mixed>>|string  $skillpointsDataOrXml
     */
    public function __construct(
        private array|string $skillpointsDataOrXml
    ) {
        $this->instantCostNormalizer = new InstantFinishCostNormalizer;
        $this->costNormalizer = new CostNormalizer;
    }

    public function id(): string
    {
        return 'skillpoints';
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array<string, mixed>>
     */
    public function recipesFor(int $productionType, array $options = []): array
    {
        if (is_array($this->skillpointsDataOrXml)) {
            return $this->skillpointsDataOrXml;
        }

        $doc = new DOMDocument;
        @$doc->loadXML($this->skillpointsDataOrXml);

        $recipes = [];
        /** @var DOMElement $spEl */
        foreach ($doc->getElementsByTagName('skillPoint') as $spEl) {
            $name = $spEl->getAttribute('id');
            if ($name === '') {
                continue;
            }

            $instantCost = $this->instantCostNormalizer->fromElement($spEl);

            $costTiers = [];
            $firstTierCosts = [];
            $firstTierDuration = 0;

            /** @var DOMNode $child */
            foreach ($spEl->childNodes as $child) {
                if ($child instanceof DOMElement && $child->nodeName === 'productionLevel') {
                    $threshold = (int) $child->getAttribute('amountProduced');
                    $duration = (int) ($child->getAttribute('productionTime') ?: 0);
                    $tierCosts = $this->costNormalizer->fromLowercaseCosts($child);

                    $costTiers[] = [
                        'threshold' => $threshold,
                        'costs' => $tierCosts,
                    ];

                    if ($threshold === 0 || $firstTierDuration === 0) {
                        $firstTierCosts = $tierCosts;
                        $firstTierDuration = $duration;
                    }
                }
            }

            // Sort cost tiers by threshold ascending
            usort($costTiers, static fn (array $a, array $b): int => $a['threshold'] <=> $b['threshold']);
            if ($costTiers !== []) {
                $firstTierCosts = $costTiers[0]['costs'];
            }

            $recipes[] = [
                'name' => $name,
                'group' => 0,
                'duration_seconds' => $firstTierDuration,
                'buff_type' => 'Timed',
                'requires_upgrade_level_min' => 0,
                'requires_upgrade_level_max' => 99,
                'requires_event' => null,
                'requires_quest' => null,
                'costs' => $firstTierCosts,
                'costs_known' => count($firstTierCosts) > 0,
                'instant_finish_cost' => $instantCost,
                'max_amount_per_order' => 1,
                'max_stacks_per_order' => 1,
                'stacks_supported' => false,
                'cost_is_lower_bound' => true,
                'cost_tiers' => $costTiers,
                'requires_player_level_min' => null,
                'output_buff_name' => null,
                'unverified_protocol' => false,
                'tier' => null,
                'unit_group' => null,
            ];
        }

        return $recipes;
    }
}
