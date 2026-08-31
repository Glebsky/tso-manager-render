<?php

declare(strict_types=1);

namespace App\Services\Game\Production\Sources;

use App\Services\Game\Production\Normalizers\CostNormalizer;
use App\Services\Game\Production\Normalizers\DurationNormalizer;
use App\Services\Game\Production\Normalizers\InstantFinishCostNormalizer;
use DOMDocument;
use DOMElement;

final readonly class CollectionRecipeSource implements RecipeSourceInterface
{
    private DurationNormalizer $durationNormalizer;

    private InstantFinishCostNormalizer $instantCostNormalizer;

    private CostNormalizer $costNormalizer;

    /**
     * @param  list<array<string, mixed>>|string  $collectionsDataOrXml
     */
    public function __construct(
        private array|string $collectionsDataOrXml
    ) {
        $this->durationNormalizer = new DurationNormalizer;
        $this->instantCostNormalizer = new InstantFinishCostNormalizer;
        $this->costNormalizer = new CostNormalizer;
    }

    public function id(): string
    {
        return 'collections';
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array<string, mixed>>
     */
    public function recipesFor(int $productionType, array $options = []): array
    {
        if (is_array($this->collectionsDataOrXml)) {
            return $this->collectionsDataOrXml;
        }

        $doc = new DOMDocument;
        @$doc->loadXML($this->collectionsDataOrXml);

        $recipes = [];
        /** @var DOMElement $colEl */
        foreach ($doc->getElementsByTagName('collection') as $colEl) {
            $name = $colEl->getAttribute('name');
            if ($name === '') {
                continue;
            }

            $duration = $this->durationNormalizer->fromElement($colEl);
            $instantCost = $this->instantCostNormalizer->fromElement($colEl);
            $costs = $this->costNormalizer->fromResources($colEl);

            $pLvl = null;
            if ($colEl->hasAttribute('pLvl') && $colEl->getAttribute('pLvl') !== '') {
                $pLvl = (int) $colEl->getAttribute('pLvl');
            } elseif ($colEl->hasAttribute('minLevel') && $colEl->getAttribute('minLevel') !== '') {
                $pLvl = (int) $colEl->getAttribute('minLevel');
            }

            $reqEvent = $colEl->hasAttribute('requiresEvent') && $colEl->getAttribute('requiresEvent') !== ''
                ? $colEl->getAttribute('requiresEvent')
                : null;

            $outBuffName = $colEl->hasAttribute('outBuffName') && $colEl->getAttribute('outBuffName') !== ''
                ? $colEl->getAttribute('outBuffName')
                : null;

            $recipes[] = [
                'name' => $name,
                'group' => 0,
                'duration_seconds' => $duration,
                'buff_type' => 'Timed',
                'requires_upgrade_level_min' => 0,
                'requires_upgrade_level_max' => 99,
                'requires_event' => $reqEvent,
                'requires_quest' => null,
                'costs' => $costs,
                'costs_known' => count($costs) > 0,
                'instant_finish_cost' => $instantCost,
                'max_amount_per_order' => 25,
                'max_stacks_per_order' => 1,
                'stacks_supported' => false,
                'cost_is_lower_bound' => false,
                'cost_tiers' => null,
                'requires_player_level_min' => $pLvl,
                'output_buff_name' => $outBuffName,
                'unverified_protocol' => true,
                'tier' => null,
                'unit_group' => null,
            ];
        }

        return $recipes;
    }
}
