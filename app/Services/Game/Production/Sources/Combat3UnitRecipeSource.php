<?php

declare(strict_types=1);

namespace App\Services\Game\Production\Sources;

use App\Services\Game\Production\Normalizers\CostNormalizer;
use App\Services\Game\Production\Normalizers\DurationNormalizer;
use App\Services\Game\Production\Normalizers\InstantFinishCostNormalizer;
use DOMDocument;
use DOMElement;
use DOMNode;

final readonly class Combat3UnitRecipeSource implements RecipeSourceInterface
{
    private DurationNormalizer $durationNormalizer;

    private InstantFinishCostNormalizer $instantCostNormalizer;

    private CostNormalizer $costNormalizer;

    /**
     * @param  list<array<string, mixed>>|string  $unitsDataOrXml
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
        return 'combat3_units';
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array<string, mixed>>
     */
    public function recipesFor(int $productionType, array $options = []): array
    {
        if (is_array($this->unitsDataOrXml)) {
            return $this->unitsDataOrXml;
        }

        $doc = new DOMDocument;
        @$doc->loadXML($this->unitsDataOrXml);

        $recipes = [];
        /** @var DOMElement $unitEl */
        foreach ($doc->getElementsByTagName('Unit') as $unitEl) {
            if (! $this->isProducible($unitEl)) {
                continue;
            }

            $name = $unitEl->getAttribute('Type') ?: $unitEl->getAttribute('type');
            if ($name === '') {
                continue;
            }

            $tierAttr = $unitEl->getAttribute('Tier') ?: $unitEl->getAttribute('tier');
            $tier = $tierAttr !== '' ? (int) $tierAttr : 3;

            $groupAttr = $unitEl->getAttribute('group');
            $unitGroup = $groupAttr !== '' ? $groupAttr : null;

            $duration = $this->durationNormalizer->fromElement($unitEl);
            $instantCost = $this->instantCostNormalizer->fromElement($unitEl);
            $costs = $this->costNormalizer->fromCapitalizedCosts($unitEl);

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
                'unverified_protocol' => true,
                'tier' => $tier,
                'unit_group' => $unitGroup,
            ];
        }

        return $recipes;
    }

    private function isProducible(DOMElement $unitEl): bool
    {
        /** @var DOMNode $child */
        foreach ($unitEl->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->nodeName) === 'properties') {
                /** @var DOMNode $prop */
                foreach ($child->childNodes as $prop) {
                    if ($prop instanceof DOMElement && strtolower($prop->nodeName) === 'property') {
                        $type = $prop->getAttribute('Type') ?: $prop->getAttribute('type');
                        if ($type === 'IsProducible') {
                            $val = $prop->getAttribute('Value') ?: $prop->getAttribute('value');

                            return $val === '1' || $val === 'true';
                        }
                    }
                }
            }
        }

        return false;
    }
}
