<?php

declare(strict_types=1);

namespace App\Services\Game\Production\Sources;

use App\Services\Game\Production\Normalizers\CostNormalizer;
use App\Services\Game\Production\Normalizers\DurationNormalizer;
use App\Services\Game\Production\Normalizers\InstantFinishCostNormalizer;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

final readonly class MilitaryUnitRecipeSource implements RecipeSourceInterface
{
    private DurationNormalizer $durationNormalizer;

    private InstantFinishCostNormalizer $instantCostNormalizer;

    private CostNormalizer $costNormalizer;

    /**
     * @param  list<array<string, mixed>>|string  $unitsDataOrXml  parsed units data or XML string of MilitaryUnit elements
     */
    public function __construct(
        private array|string $unitsDataOrXml
    ) {
        $this->durationNormalizer = new DurationNormalizer;
        $this->instantCostNormalizer = new InstantFinishCostNormalizer;
        $this->costNormalizer = new CostNormalizer;
    }

    public function id(): string
    {
        return 'military_units';
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array<string, mixed>>
     */
    public function recipesFor(int $productionType, array $options = []): array
    {
        if (! isset($options['elite']) || ! is_bool($options['elite'])) {
            throw new InvalidArgumentException("MilitaryUnitRecipeSource requires a boolean 'elite' option for productionType {$productionType}");
        }

        $targetElite = $options['elite'];

        if (is_array($this->unitsDataOrXml)) {
            // Already parsed unit structures
            $result = [];
            foreach ($this->unitsDataOrXml as $unit) {
                $isElite = (bool) ($unit['is_elite'] ?? false);
                if ($isElite === $targetElite) {
                    $result[] = $unit['recipe'];
                }
            }

            return $result;
        }

        // Parse from XML string
        $doc = new DOMDocument;
        @$doc->loadXML($this->unitsDataOrXml);

        $recipes = [];
        /** @var DOMElement $unitEl */
        foreach ($doc->getElementsByTagName('MilitaryUnit') as $unitEl) {
            $produceable = $unitEl->getAttribute('produceable');
            if ($produceable !== 'true' && $produceable !== '1') {
                continue;
            }

            $isElite = $unitEl->hasAttribute('isElite') && ($unitEl->getAttribute('isElite') === 'true' || $unitEl->getAttribute('isElite') === '1');
            if ($isElite !== $targetElite) {
                continue;
            }

            $name = $unitEl->getAttribute('type');
            if ($name === '') {
                continue;
            }

            $duration = $this->durationNormalizer->fromElement($unitEl);
            $instantCost = $this->instantCostNormalizer->fromElement($unitEl);
            $costs = $this->costNormalizer->fromCostsBlock($unitEl);

            $recipes[] = [
                'name' => $name,
                'group' => 0,
                'duration_seconds' => $duration,
                'buff_type' => 'Timed',
                'requires_upgrade_level_min' => 0,
                'requires_upgrade_level_max' => 99,
                'requires_event' => null,
                'requires_quest' => null,
                'costs' => $costs,
                'costs_known' => count($costs) > 0,
                'instant_finish_cost' => $instantCost,
                'max_amount_per_order' => 25,
                'max_stacks_per_order' => 200,
                'stacks_supported' => true,
                'cost_is_lower_bound' => false,
                'cost_tiers' => null,
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
