<?php

declare(strict_types=1);

namespace App\Console\Commands;

use DOMDocument;
use DOMElement;
use Illuminate\Console\Command;
use RuntimeException;
use XMLReader;

final class ImportTradeablesCatalog extends Command
{
    /**
     * @var string
     */
    protected $signature = 'tso:import-tradeables-catalog
                            {--base-dir=docs/references/xml : Base directory for XML files}
                            {--adventures= : Path to adventures XML}
                            {--buffs= : Path to buffs/gfx XML}
                            {--output=config/game_tradeables.php : Output config path}
                            {--dry-run : Only parse and report stats without writing file}';

    /**
     * @var string
     */
    protected $description = 'Import adventures and buffs tradeables catalog from game XML files into config';

    public function handle(): int
    {
        $baseDir = (string) $this->option('base-dir');
        $adventuresPath = (string) ($this->option('adventures') ?: $this->findXmlFile($baseDir, 'adventure.xml'));
        $buffsPath = (string) ($this->option('buffs') ?: $this->findBuffsFile($baseDir));
        $outputPath = (string) $this->option('output');
        $isDryRun = (bool) $this->option('dry-run');

        $this->info("Parsing adventures from: {$adventuresPath}");
        $adventures = $this->parseAdventures($adventuresPath);

        $this->info("Parsing buffs from: {$buffsPath}");
        $buffs = $this->parseBuffs($buffsPath);

        ksort($adventures);
        ksort($buffs);

        $tradableAdventures = count(array_filter($adventures, fn (array $item): bool => $item['tradable'] === true));
        $tradableBuffs = count(array_filter($buffs, fn (array $item): bool => $item['tradable'] === true));

        $this->table(
            ['Catalog', 'Total Items', 'Tradable Items'],
            [
                ['Adventures', count($adventures), $tradableAdventures],
                ['Buffs', count($buffs), $tradableBuffs],
            ]
        );

        if ($isDryRun) {
            $this->warn('DRY RUN: config file not written.');

            return self::SUCCESS;
        }

        $this->writeConfigFile($outputPath, $adventures, $buffs);
        $this->info("Catalog written successfully to {$outputPath}");

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{id: int, tradable: bool, level_range: string, difficulty: int}>
     */
    public function parseAdventures(string $path): array
    {
        if (! file_exists($path)) {
            throw new RuntimeException("Adventures XML not found: {$path}");
        }

        $doc = new DOMDocument;
        $doc->loadXML((string) file_get_contents($path), LIBXML_NONET | LIBXML_COMPACT);

        $adventures = [];
        $elements = $doc->getElementsByTagName('Adventure');

        /** @var DOMElement $element */
        foreach ($elements as $element) {
            $name = $element->getAttribute('name');
            if ($name === '') {
                continue;
            }

            $adventures[$name] = [
                'id' => (int) $element->getAttribute('id'),
                'tradable' => $element->getAttribute('tradable') === 'true',
                'level_range' => $element->getAttribute('levelRange'),
                'difficulty' => (int) $element->getAttribute('difficulty'),
            ];
        }

        return $adventures;
    }

    /**
     * @return array<string, array{id: int, tradable: bool, resource?: string, amount?: int}>
     */
    public function parseBuffs(string $path): array
    {
        if (! file_exists($path)) {
            throw new RuntimeException("Buffs XML not found: {$path}");
        }

        $reader = new XMLReader;
        $reader->open($path, null, LIBXML_NONET | LIBXML_COMPACT);

        $buffs = [];

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->name === 'Buff') {
                $name = (string) $reader->getAttribute('name');
                if ($name === '') {
                    continue;
                }

                $id = (int) $reader->getAttribute('id');
                $tradable = $reader->getAttribute('tradable') === 'true';
                $resource = $reader->getAttribute('resourceName');
                $amountAttr = $reader->getAttribute('amount');

                $entry = [
                    'id' => $id,
                    'tradable' => $tradable,
                ];

                if ($resource !== null && $resource !== '') {
                    $entry['resource'] = $resource;
                }

                if ($amountAttr !== null && $amountAttr !== '') {
                    $entry['amount'] = (int) $amountAttr;
                }

                $buffs[$name] = $entry;
            }
        }

        $reader->close();

        return $buffs;
    }

    /**
     * @param  array<string, mixed>  $adventures
     * @param  array<string, mixed>  $buffs
     */
    private function writeConfigFile(string $outputPath, array $adventures, array $buffs): void
    {
        $exportedAdventures = $this->prettyVarExport($adventures, 1);
        $exportedBuffs = $this->prettyVarExport($buffs, 1);

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    'adventures' => {$exportedAdventures},\n    'buffs' => {$exportedBuffs},\n];\n";

        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($outputPath, $content);
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

    private function findXmlFile(string $baseDir, string $filename): string
    {
        $direct = $baseDir.'/'.$filename;
        if (file_exists($direct)) {
            return $direct;
        }

        throw new RuntimeException("Could not find {$filename} in {$baseDir}");
    }

    private function findBuffsFile(string $baseDir): string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $candidates = [];

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                $sample = (string) file_get_contents($file->getPathname(), false, null, 0, 32768);
                if (str_contains($sample, '<Buff ') || str_contains($sample, '<Buffs')) {
                    $candidates[] = $file->getPathname();
                } else {
                    $full = (string) file_get_contents($file->getPathname());
                    if (str_contains($full, '<Buff ') || str_contains($full, '<Buffs')) {
                        $candidates[] = $file->getPathname();
                    }
                }
            }
        }

        if (! empty($candidates)) {
            usort($candidates, fn (string $a, string $b): int => filesize($b) <=> filesize($a));

            return $candidates[0];
        }

        throw new RuntimeException("Could not find buffs XML in {$baseDir}");
    }
}
