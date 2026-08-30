<?php

declare(strict_types=1);

namespace App\Console\Commands;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Console\Command;
use XMLReader;

final class ImportProductionCatalog extends Command
{
    /**
     * @var string
     */
    protected $signature = 'tso:import-production-catalog
                            {--icons=docs/references/icons.xml : Path to icons.xml}
                            {--globals=docs/references/globals.xml : Path to globals.xml}
                            {--output=config/game_production.php : Output config path}
                            {--dry-run : Only parse and report stats without writing file}';

    /**
     * @var string
     */
    protected $description = 'Import production buildings and recipes catalog from game XML files into config';

    public function handle(): int
    {
        $iconsPath = (string) $this->option('icons');
        $globalsPath = (string) $this->option('globals');
        $outputPath = (string) $this->option('output');
        $dryRun = (bool) $this->option('dry-run');

        if (! file_exists($iconsPath)) {
            $this->error("Icons XML file not found: {$iconsPath}");

            return self::FAILURE;
        }

        if (! file_exists($globalsPath)) {
            $this->error("Globals XML file not found: {$globalsPath}");

            return self::FAILURE;
        }

        $this->info('Parsing icons.xml for producer buildings...');
        $producers = $this->parseProducers($iconsPath);
        $this->info(sprintf('Found %d producer buildings.', count($producers)));

        $this->info('Parsing globals.xml for buff pool and timed production lists...');
        [$buffPool, $timedLists] = $this->parseGlobals($globalsPath);
        $this->info(sprintf('Found %d produceable buffs in dynamic pool.', count($buffPool)));
        $this->info(sprintf('Found %d timed production lists.', count($timedLists)));

        $recipesByProductionType = [];
        $metadataByProductionType = [];

        // All distinct production types from producers and timed lists
        $allProductionTypes = array_unique(array_merge(
            array_values($producers),
            array_keys($timedLists)
        ));
        sort($allProductionTypes, SORT_NUMERIC);

        foreach ($allProductionTypes as $type) {
            $listData = $timedLists[$type] ?? null;
            $listType = $listData['type'] ?? 'hardcoded';
            $explicitRecipes = $listData['recipes'] ?? [];

            if ($type === 1) {
                // ProvisionHouse uses dynamic buff pool (Branch B)
                $recipes = array_values($buffPool);
                $source = 'buff_pool';
            } elseif ($explicitRecipes !== []) {
                $recipes = $explicitRecipes;
                $source = 'explicit_list';
            } elseif ($listType === 'culturebuilding' || $type === 12 || $type === 13) {
                // If explicit list is empty for culture building, fallback to dynamic buff pool (Branch D)
                $recipes = $explicitRecipes;
                $source = 'buff_pool';
            } else {
                $recipes = $explicitRecipes;
                $source = 'explicit_list';
            }

            // Sort recipes deterministically by name
            usort($recipes, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

            // Sort costs deterministically inside each recipe
            foreach ($recipes as &$recipe) {
                if (isset($recipe['costs']) && is_array($recipe['costs']) && count($recipe['costs']) > 1) {
                    usort($recipe['costs'], static fn (array $a, array $b): int => strcmp((string) $a['resource'], (string) $b['resource']));
                }
            }
            unset($recipe);

            if ($recipes !== []) {
                $recipesByProductionType[$type] = $recipes;
                $metadataByProductionType[$type] = [
                    'recipe_source' => $source,
                    'list_type' => $listType,
                ];
            }
        }

        // Sort producers by building name
        ksort($producers, SORT_STRING);
        ksort($recipesByProductionType, SORT_NUMERIC);
        ksort($metadataByProductionType, SORT_NUMERIC);

        $totalRecipes = array_sum(array_map('count', $recipesByProductionType));
        $this->info(sprintf('Catalog compiled: %d producers, %d production types with %d total recipe entries.', count($producers), count($recipesByProductionType), $totalRecipes));

        if ($dryRun) {
            $this->warn('[Dry Run] Output file was not written.');

            return self::SUCCESS;
        }

        $content = $this->renderPhpConfigFile($producers, $metadataByProductionType, $recipesByProductionType);

        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($outputPath, $content);
        $this->info("Catalog written to {$outputPath}");

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function parseProducers(string $iconsPath): array
    {
        $producers = [];
        $reader = new XMLReader;
        $reader->open($iconsPath);

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'Building') {
                $productionTypeAttr = $reader->getAttribute('productionType');
                if ($productionTypeAttr !== null) {
                    $pt = (int) $productionTypeAttr;
                    if ($pt >= 0) {
                        $name = (string) $reader->getAttribute('name');
                        if ($name !== '') {
                            $producers[$name] = $pt;
                        }
                    }
                }
            }
        }

        $reader->close();

        return $producers;
    }

    /**
     * @return array{0: array<string, array<string, mixed>>, 1: array<int, array{type: string, recipes: list<array<string, mixed>>}>}
     */
    private function parseGlobals(string $globalsPath): array
    {
        $buffPool = [];
        $timedLists = [];

        $reader = new XMLReader;
        $reader->open($globalsPath);

        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT) {
                continue;
            }

            if ($reader->name === 'Buff') {
                $produceable = $reader->getAttribute('produceable');
                if ($produceable === 'true' || $produceable === '1') {
                    $name = (string) $reader->getAttribute('name');
                    $groupAttr = $reader->getAttribute('group');
                    $group = is_numeric($groupAttr) ? (int) $groupAttr : (string) $groupAttr;
                    $duration = (int) ($reader->getAttribute('productionTime') ?: 0);
                    $buffType = (string) ($reader->getAttribute('buffType') ?: 'Timed');
                    $minLvl = $reader->getAttribute('requiresUpgradeLevelMin');
                    $maxLvl = $reader->getAttribute('requiresUpgradeLevelMax');
                    $reqEvent = $reader->getAttribute('requiresEvent');
                    $reqQuest = $reader->getAttribute('requiresQuest');

                    $outerXml = $reader->readOuterXml();
                    $costs = $this->extractCostsFromXml($outerXml);

                    $buffPool[$name] = [
                        'name' => $name,
                        'group' => $group,
                        'duration_seconds' => $duration,
                        'buff_type' => $buffType,
                        'requires_upgrade_level_min' => $minLvl !== null ? (int) $minLvl : 0,
                        'requires_upgrade_level_max' => $maxLvl !== null ? (int) $maxLvl : 99,
                        'requires_event' => $reqEvent ?: null,
                        'requires_quest' => $reqQuest ?: null,
                        'costs' => $costs,
                        'costs_known' => count($costs) > 0,
                    ];
                }
            } elseif ($reader->name === 'TimedProductionList') {
                $listId = (int) $reader->getAttribute('id');
                $listType = (string) ($reader->getAttribute('type') ?: 'hardcoded');
                $listXml = $reader->readOuterXml();

                $recipes = $this->parseTimedProductionListXml($listXml);
                $timedLists[$listId] = [
                    'type' => $listType,
                    'recipes' => $recipes,
                ];
            }
        }

        $reader->close();

        return [$buffPool, $timedLists];
    }

    /**
     * @return list<array{resource: string, count: int}>
     */
    private function extractCostsFromXml(string $xml): array
    {
        $costs = [];
        if (preg_match_all('/<cost\s+name="([^"]+)"\s+count="([^"]+)"/i', $xml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $costs[] = [
                    'resource' => $m[1],
                    'count' => (int) $m[2],
                ];
            }
        }

        return $costs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseTimedProductionListXml(string $listXml): array
    {
        $doc = new DOMDocument;
        @$doc->loadXML($listXml);

        $recipes = [];
        /** @var DOMElement $tp */
        foreach ($doc->getElementsByTagName('TimedProduction') as $tp) {
            $name = $tp->getAttribute('name') ?: $tp->getAttribute('productionName');
            if ($name === '') {
                continue;
            }

            $groupAttr = $tp->getAttribute('group');
            $group = is_numeric($groupAttr) ? (int) $groupAttr : $groupAttr;
            $duration = (int) ($tp->getAttribute('duration') ?: $tp->getAttribute('productionTime') ?: 0);
            $minLvl = $tp->hasAttribute('requiresUpgradeLevelMin') ? (int) $tp->getAttribute('requiresUpgradeLevelMin') : 0;
            $maxLvl = $tp->hasAttribute('requiresUpgradeLevelMax') ? (int) $tp->getAttribute('requiresUpgradeLevelMax') : 99;
            $reqEvent = $tp->hasAttribute('requiresEvent') ? $tp->getAttribute('requiresEvent') : null;
            $reqQuest = $tp->hasAttribute('requiresQuest') ? $tp->getAttribute('requiresQuest') : null;

            $costs = [];
            $costsTags = $tp->getElementsByTagName('Costs');
            if ($costsTags->length > 0) {
                $firstCostsTag = $costsTags->item(0);
                if ($firstCostsTag !== null) {
                    /** @var DOMNode $child */
                    foreach ($firstCostsTag->childNodes as $child) {
                        if ($child instanceof DOMElement && strtolower($child->nodeName) === 'cost') {
                            $costs[] = [
                                'resource' => $child->getAttribute('name'),
                                'count' => (int) $child->getAttribute('count'),
                            ];
                        }
                    }
                }
            }

            $recipes[] = [
                'name' => $name,
                'group' => $group,
                'duration_seconds' => $duration,
                'buff_type' => 'Timed',
                'requires_upgrade_level_min' => $minLvl,
                'requires_upgrade_level_max' => $maxLvl,
                'requires_event' => $reqEvent ?: null,
                'requires_quest' => $reqQuest ?: null,
                'costs' => $costs,
                'costs_known' => count($costs) > 0,
            ];
        }

        return $recipes;
    }

    /**
     * @param  array<string, int>  $producers
     * @param  array<int, array<string, mixed>>  $metadata
     * @param  array<int, list<array<string, mixed>>>  $recipes
     */
    private function renderPhpConfigFile(array $producers, array $metadata, array $recipes): string
    {
        $producersExport = $this->prettyVarExport($producers, 1);
        $metadataExport = $this->prettyVarExport($metadata, 1);
        $recipesExport = $this->prettyVarExport($recipes, 1);

        return <<<PHP
<?php

declare(strict_types=1);

// THIS FILE IS AUTOMATICALLY GENERATED. DO NOT EDIT MANUALLY.
// Command: php artisan tso:import-production-catalog

return [
    'producers' => {$producersExport},
    'metadata' => {$metadataExport},
    'recipes' => {$recipesExport},
];

PHP;
    }

    private function prettyVarExport(mixed $expression, int $level = 0): string
    {
        $indent = str_repeat('    ', $level);
        $subIndent = str_repeat('    ', $level + 1);

        if (is_array($expression)) {
            if ($expression === []) {
                return '[]';
            }

            $isAssoc = array_keys($expression) !== range(0, count($expression) - 1);
            $lines = [];

            foreach ($expression as $key => $value) {
                $renderedValue = $this->prettyVarExport($value, $level + 1);
                if ($isAssoc) {
                    $keyExport = is_int($key) ? (string) $key : "'".addcslashes($key, "'\\")."'";
                    $lines[] = "{$subIndent}{$keyExport} => {$renderedValue},";
                } else {
                    $lines[] = "{$subIndent}{$renderedValue},";
                }
            }

            return "[\n".implode("\n", $lines)."\n{$indent}]";
        }

        if (is_string($expression)) {
            return "'".addcslashes($expression, "'\\")."'";
        }

        if (is_int($expression) || is_float($expression)) {
            return (string) $expression;
        }

        if (is_bool($expression)) {
            return $expression ? 'true' : 'false';
        }

        if ($expression === null) {
            return 'null';
        }

        return var_export($expression, true);
    }
}
