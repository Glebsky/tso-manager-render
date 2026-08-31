<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Game\Production\GameXmlLocator;
use App\Services\Game\Production\Normalizers\CostNormalizer;
use App\Services\Game\Production\Normalizers\DurationNormalizer;
use App\Services\Game\Production\Normalizers\InstantFinishCostNormalizer;
use App\Services\Game\Production\Sources\BuffGroupRecipeSource;
use App\Services\Game\Production\Sources\BuffPoolRecipeSource;
use App\Services\Game\Production\Sources\CollectionRecipeSource;
use App\Services\Game\Production\Sources\Combat3UnitRecipeSource;
use App\Services\Game\Production\Sources\ExplicitListRecipeSource;
use App\Services\Game\Production\Sources\MilitaryUnitRecipeSource;
use App\Services\Game\Production\Sources\RecipeSourceInterface;
use App\Services\Game\Production\Sources\SkillPointRecipeSource;
use App\Services\Game\Production\Sources\UnsupportedRecipeSource;
use DOMDocument;
use DOMElement;
use Illuminate\Console\Command;
use RuntimeException;
use XMLReader;

final class ImportProductionCatalog extends Command
{
    /**
     * @var string
     */
    protected $signature = 'tso:import-production-catalog
                            {--base-dir=docs/references/xml : Base directory for XML files}
                            {--icons= : Path to icons XML file}
                            {--globals= : Path to globals XML file}
                            {--skillpoints= : Path to skillpoints XML file}
                            {--collections= : Path to collections XML file}
                            {--units= : Path to units XML file}
                            {--output=config/game_production.php : Output config path}
                            {--dry-run : Only parse and report stats without writing file}';

    /**
     * @var string
     */
    protected $description = 'Import production buildings and recipes catalog from game XML files into config';

    private DurationNormalizer $durationNormalizer;

    private InstantFinishCostNormalizer $instantCostNormalizer;

    private CostNormalizer $costNormalizer;

    public function __construct()
    {
        parent::__construct();
        $this->durationNormalizer = new DurationNormalizer;
        $this->instantCostNormalizer = new InstantFinishCostNormalizer;
        $this->costNormalizer = new CostNormalizer;
    }

    public function handle(): int
    {
        $baseDir = (string) ($this->option('base-dir') ?: 'docs/references/xml');
        $outputPath = (string) $this->option('output');
        $dryRun = (bool) $this->option('dry-run');

        $explicitPaths = [
            GameXmlLocator::ROLE_ICONS => $this->option('icons') ? (string) $this->option('icons') : null,
            GameXmlLocator::ROLE_GLOBALS => $this->option('globals') ? (string) $this->option('globals') : null,
            GameXmlLocator::ROLE_SKILLPOINTS => $this->option('skillpoints') ? (string) $this->option('skillpoints') : null,
            GameXmlLocator::ROLE_COLLECTIONS => $this->option('collections') ? (string) $this->option('collections') : null,
            GameXmlLocator::ROLE_UNITS => $this->option('units') ? (string) $this->option('units') : null,
        ];

        // Locate XML files
        $locator = new GameXmlLocator;
        try {
            // Fallback for tests passing only --icons and --globals without base-dir or other files
            if ($explicitPaths[GameXmlLocator::ROLE_ICONS] && $explicitPaths[GameXmlLocator::ROLE_GLOBALS] && ! is_dir($baseDir)) {
                $locatedFiles = [
                    GameXmlLocator::ROLE_ICONS => $explicitPaths[GameXmlLocator::ROLE_ICONS],
                    GameXmlLocator::ROLE_GLOBALS => $explicitPaths[GameXmlLocator::ROLE_GLOBALS],
                    GameXmlLocator::ROLE_SKILLPOINTS => $explicitPaths[GameXmlLocator::ROLE_SKILLPOINTS] ?? '',
                    GameXmlLocator::ROLE_COLLECTIONS => $explicitPaths[GameXmlLocator::ROLE_COLLECTIONS] ?? '',
                    GameXmlLocator::ROLE_UNITS => $explicitPaths[GameXmlLocator::ROLE_UNITS] ?? '',
                ];
            } else {
                $locatedFiles = $locator->locateAll($baseDir, $explicitPaths);
            }
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $iconsPath = $locatedFiles[GameXmlLocator::ROLE_ICONS] ?? '';
        $globalsPath = $locatedFiles[GameXmlLocator::ROLE_GLOBALS] ?? '';
        $skillpointsPath = $locatedFiles[GameXmlLocator::ROLE_SKILLPOINTS] ?? '';
        $collectionsPath = $locatedFiles[GameXmlLocator::ROLE_COLLECTIONS] ?? '';
        $unitsPath = $locatedFiles[GameXmlLocator::ROLE_UNITS] ?? '';

        $this->info("Loading icons from {$iconsPath}...");
        $producers = $this->parseProducers($iconsPath);
        $this->info(sprintf('Found %d producer buildings.', count($producers)));

        $this->info("Loading globals from {$globalsPath}...");
        [$buffPool, $timedLists, $militaryUnits] = $this->parseGlobals($globalsPath);
        $this->info(sprintf('Found %d produceable buffs, %d timed lists.', count($buffPool), count($timedLists)));

        $skillpointsXml = $skillpointsPath !== '' && file_exists($skillpointsPath) ? (string) file_get_contents($skillpointsPath) : '';
        $collectionsXml = $collectionsPath !== '' && file_exists($collectionsPath) ? (string) file_get_contents($collectionsPath) : '';
        $unitsXml = $unitsPath !== '' && file_exists($unitsPath) ? (string) file_get_contents($unitsPath) : '';

        /** @var array<string, RecipeSourceInterface> $sourcesRegistry */
        $sourcesRegistry = [
            'explicit_list' => new ExplicitListRecipeSource($timedLists),
            'buff_pool' => new BuffPoolRecipeSource($buffPool),
            'buff_group' => new BuffGroupRecipeSource($buffPool),
            'military_units' => new MilitaryUnitRecipeSource($militaryUnits),
            'skillpoints' => new SkillPointRecipeSource($skillpointsXml),
            'collections' => new CollectionRecipeSource($collectionsXml),
            'combat3_units' => new Combat3UnitRecipeSource($unitsXml),
        ];

        // Load configuration map
        $sourcesConfigFile = config_path('game_production_sources.php');
        /** @var array{sources: array<int|string, array{source: string, options?: array<string, mixed>}>, stacks?: array<string, mixed>} $sourcesConfig */
        $sourcesConfig = file_exists($sourcesConfigFile)
            ? (array) require $sourcesConfigFile
            : [
                'sources' => [
                    0 => ['source' => 'military_units', 'options' => ['elite' => false]],
                    1 => ['source' => 'buff_pool', 'options' => []],
                    2 => ['source' => 'skillpoints', 'options' => []],
                    4 => ['source' => 'collections', 'options' => []],
                    6 => ['source' => 'buff_group', 'options' => ['group' => 5]],
                    7 => ['source' => 'combat3_units', 'options' => []],
                    8 => ['source' => 'military_units', 'options' => ['elite' => true]],
                    11 => ['source' => 'buff_group', 'options' => ['group' => 11]],
                    'default' => ['source' => 'explicit_list', 'options' => []],
                ],
                'stacks' => [
                    'max_amount' => 25,
                    'max_stacks' => 200,
                    'unsupported_types' => [2 => '', 4 => '', 27 => ''],
                    'disable_for_list_type' => ['culturebuilding'],
                ],
            ];

        $configuredSources = $sourcesConfig['sources'];
        $stacksConfig = $sourcesConfig['stacks'] ?? [];

        $allProductionTypes = array_unique(array_merge(
            array_values($producers),
            array_keys($timedLists),
            array_filter(array_keys($configuredSources), 'is_int')
        ));
        sort($allProductionTypes, SORT_NUMERIC);

        $recipesByProductionType = [];
        $metadataByProductionType = [];

        foreach ($allProductionTypes as $type) {
            $listData = $timedLists[$type] ?? null;
            $listType = $listData['type'] ?? 'hardcoded';

            $sourceConfig = $configuredSources[$type] ?? $configuredSources['default'] ?? ['source' => 'explicit_list', 'options' => []];
            $sourceId = (string) $sourceConfig['source'];
            $options = (array) ($sourceConfig['options'] ?? []);

            if ($sourceId === 'unsupported') {
                $reason = (string) ($options['reason'] ?? 'not_supported');
                $sourceInstance = new UnsupportedRecipeSource($reason);
            } elseif (isset($sourcesRegistry[$sourceId])) {
                $sourceInstance = $sourcesRegistry[$sourceId];
            } else {
                $this->warn("Unknown recipe source '{$sourceId}' for productionType {$type}");
                $sourceInstance = $sourcesRegistry['explicit_list'];
            }

            $recipes = $sourceInstance->recipesFor($type, $options);

            // Compute stacks policy
            $stacksSupported = $this->computeStacksSupported($type, $sourceId, $listType, $stacksConfig);
            $maxStacks = $stacksSupported ? (int) ($stacksConfig['max_stacks'] ?? 200) : 1;
            $maxAmount = ($sourceId === 'skillpoints') ? 1 : (int) ($stacksConfig['max_amount'] ?? 25);

            foreach ($recipes as &$recipe) {
                $recipe['stacks_supported'] = $stacksSupported;
                $recipe['max_stacks_per_order'] = $maxStacks;
                $recipe['max_amount_per_order'] = $maxAmount;

                // Ensure is_population flag exists on all cost items
                if (isset($recipe['costs']) && is_array($recipe['costs'])) {
                    foreach ($recipe['costs'] as &$c) {
                        if (! isset($c['is_population'])) {
                            $c['is_population'] = ($c['resource'] ?? '') === 'Population';
                        }
                    }
                    unset($c);

                    if (count($recipe['costs']) > 1) {
                        usort($recipe['costs'], static fn (array $a, array $b): int => strcmp((string) $a['resource'], (string) $b['resource']));
                    }
                }
            }
            unset($recipe);

            // Sort recipes deterministically by name
            usort($recipes, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

            if ($recipes !== [] || str_starts_with($sourceInstance->id(), 'unsupported:')) {
                $recipesByProductionType[$type] = $recipes;
                $metadataByProductionType[$type] = [
                    'recipe_source' => $sourceInstance->id(),
                    'list_type' => $listType,
                ];
            } else {
                // Check if this empty list is expected
                $expectedEmptyLists = [0, 1, 2, 3, 4, 6, 7, 8, 11];
                if (! in_array($type, $expectedEmptyLists, true) && in_array($type, $producers, true)) {
                    $this->warn("Production type {$type} has a producer building but 0 recipes were found.");
                }
            }
        }

        // Sort producers by building name
        ksort($producers, SORT_STRING);
        ksort($recipesByProductionType, SORT_NUMERIC);
        ksort($metadataByProductionType, SORT_NUMERIC);

        $totalRecipes = array_sum(array_map('count', $recipesByProductionType));
        $this->info(sprintf(
            'Catalog compiled: %d producers, %d production types with %d total recipe entries.',
            count($producers),
            count($recipesByProductionType),
            $totalRecipes
        ));

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
     * Compute stacks_supported per stacks-policy.md §5:
     * 1. type in unsupported_types -> false
     * 2. source is skillpoints or collections -> false
     * 3. list_type in disable_for_list_type -> false
     * 4. otherwise -> true
     *
     * @param  array<string, mixed>  $stacksConfig
     */
    private function computeStacksSupported(int $type, string $sourceId, string $listType, array $stacksConfig): bool
    {
        $unsupportedTypes = array_keys((array) ($stacksConfig['unsupported_types'] ?? []));
        if (in_array($type, $unsupportedTypes, true)) {
            return false;
        }

        if ($sourceId === 'skillpoints' || $sourceId === 'collections') {
            return false;
        }

        $disabledListTypes = (array) ($stacksConfig['disable_for_list_type'] ?? ['culturebuilding']);
        if (in_array($listType, $disabledListTypes, true)) {
            return false;
        }

        return true;
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
     * @return array{
     *     0: array<string, array<string, mixed>>,
     *     1: array<int, array{type: string, recipes: list<array<string, mixed>>}>,
     *     2: list<array{is_elite: bool, recipe: array<string, mixed>}>
     * }
     */
    private function parseGlobals(string $globalsPath): array
    {
        $buffPool = [];
        $timedLists = [];
        $militaryUnits = [];

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
                    $instantCostAttr = $reader->getAttribute('instantBuildCosts');
                    $instantCost = $instantCostAttr !== null && $instantCostAttr !== '' ? (int) $instantCostAttr : null;
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
            } elseif ($reader->name === 'TimedProductionList') {
                $listId = (int) $reader->getAttribute('id');
                $listType = (string) ($reader->getAttribute('type') ?: 'hardcoded');
                $listXml = $reader->readOuterXml();

                $recipes = $this->parseTimedProductionListXml($listXml);
                $timedLists[$listId] = [
                    'type' => $listType,
                    'recipes' => $recipes,
                ];
            } elseif ($reader->name === 'MilitaryUnit') {
                $produceable = $reader->getAttribute('produceable');
                if ($produceable === 'true' || $produceable === '1') {
                    $isElite = $reader->getAttribute('isElite') === 'true' || $reader->getAttribute('isElite') === '1';
                    $unitDoc = new DOMDocument;
                    @$unitDoc->loadXML($reader->readOuterXml());
                    $unitEl = $unitDoc->documentElement;
                    if ($unitEl instanceof DOMElement) {
                        $name = (string) ($unitEl->getAttribute('type') ?: $unitEl->getAttribute('name'));
                        if ($name !== '') {
                            $duration = $this->durationNormalizer->fromElement($unitEl);
                            $instantCost = $this->instantCostNormalizer->fromElement($unitEl);
                            $costs = $this->costNormalizer->fromCostsBlock($unitEl);

                            $militaryUnits[] = [
                                'is_elite' => $isElite,
                                'recipe' => [
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
                                ],
                            ];
                        }
                    }
                }
            }
        }

        $reader->close();

        return [$buffPool, $timedLists, $militaryUnits];
    }

    /**
     * @return list<array{resource: string, count: int, is_population: bool}>
     */
    private function extractCostsFromXml(string $xml): array
    {
        $costs = [];
        if (preg_match_all('/<cost\s+name="([^"]+)"\s+count="([^"]+)"/i', $xml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $costs[] = [
                    'resource' => $m[1],
                    'count' => (int) $m[2],
                    'is_population' => $m[1] === 'Population',
                ];
            }
        }

        if (count($costs) > 1) {
            usort($costs, static fn (array $a, array $b): int => strcmp((string) $a['resource'], (string) $b['resource']));
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
            $duration = $this->durationNormalizer->fromElement($tp);
            $instantCost = $this->instantCostNormalizer->fromElement($tp);
            $minLvl = $tp->hasAttribute('requiresUpgradeLevelMin') ? (int) $tp->getAttribute('requiresUpgradeLevelMin') : 0;
            $maxLvl = $tp->hasAttribute('requiresUpgradeLevelMax') ? (int) $tp->getAttribute('requiresUpgradeLevelMax') : 99;
            $reqEvent = $tp->hasAttribute('requiresEvent') ? $tp->getAttribute('requiresEvent') : null;
            $reqQuest = $tp->hasAttribute('requiresQuest') ? $tp->getAttribute('requiresQuest') : null;

            $costs = $this->costNormalizer->fromCostsBlock($tp);

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
