<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Lang\GameLangFileWriter;
use App\Services\Lang\LangImportException;
use App\Services\Lang\LangImportResult;
use Illuminate\Console\Command;

class LangSyncUkCommand extends Command
{
    private const array TARGET_SECTIONS = ['RES', 'BUI', 'LAB', 'SPE', 'ADN'];

    protected $signature = 'tso:lang:sync-uk
        {--lang-dir= : Override the base lang directory}
        {--cache-file= : Path to translation cache JSON}';

    protected $description = 'Sync and generate Ukrainian game translations in lang/uk/game.php from RU/EN catalogs';

    public function handle(GameLangFileWriter $writer): int
    {
        $baseDirectory = (string) ($this->option('lang-dir') ?: lang_path());
        $ruFile = $baseDirectory.'/ru/game.php';
        $enFile = $baseDirectory.'/en/game.php';
        $ukFile = $baseDirectory.'/uk/game.php';

        if (! is_file($ruFile)) {
            $this->error("Russian game catalog not found: {$ruFile}");

            return self::FAILURE;
        }

        /** @var array<string, array<string, string>> $ruCatalog */
        $ruCatalog = require $ruFile;
        /** @var array<string, array<string, string>> $enCatalog */
        $enCatalog = is_file($enFile) ? require $enFile : [];
        /** @var array<string, array<string, string>> $ukCatalog */
        $ukCatalog = is_file($ukFile) ? require $ukFile : [];

        $cachePath = (string) ($this->option('cache-file') ?: storage_path('app/uk_translation_cache.json'));
        /** @var array<string, string> $cache */
        $cache = [];

        if (is_file($cachePath)) {
            $cacheJson = (string) file_get_contents($cachePath);
            $decoded = json_decode($cacheJson, true);
            if (is_array($decoded)) {
                $cache = $decoded;
            }
        }

        $finalSections = [];
        $totalEntries = 0;
        $preservedUk = 0;
        $fromCache = 0;
        $fallbackEn = 0;

        foreach (self::TARGET_SECTIONS as $sec) {
            $finalSections[$sec] = [];
            $ruSec = $ruCatalog[$sec] ?? [];
            $enSec = $enCatalog[$sec] ?? [];
            $ukSec = $ukCatalog[$sec] ?? [];

            /** @var array<string, bool> $allKeyMap */
            $allKeyMap = array_fill_keys(array_merge(array_keys($ruSec), array_keys($enSec)), true);
            $allKeys = array_keys($allKeyMap);
            sort($allKeys, SORT_STRING);

            foreach ($allKeys as $key) {
                // 1. Existing manual translation in UK
                if (isset($ukSec[$key]) && trim($ukSec[$key]) !== '') {
                    $finalSections[$sec][$key] = $ukSec[$key];
                    $preservedUk++;
                    $totalEntries++;

                    continue;
                }

                $ruText = $ruSec[$key] ?? '';
                $enText = $enSec[$key] ?? '';
                $srcText = $ruText !== '' ? $ruText : $enText;

                if ($srcText === '') {
                    continue;
                }

                if (isset($cache[$srcText]) && $cache[$srcText] !== '') {
                    $finalSections[$sec][$key] = $cache[$srcText];
                    $fromCache++;
                } else {
                    $finalSections[$sec][$key] = $srcText;
                    $fallbackEn++;
                }

                $totalEntries++;
            }
        }

        $result = new LangImportResult(
            sourceLocale: 'uk',
            sections: $finalSections,
            importedEntries: $totalEntries,
            skippedEmptyIds: 0,
            skippedEmptyTexts: 0,
            deduplicatedEntries: 0,
            conflicts: [],
        );

        try {
            $target = $writer->write($result, 'uk', 'ru/game.php + en/game.php (synced)', $baseDirectory.'/uk');
        } catch (LangImportException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Successfully synced Ukrainian translations into {$target}");
        $this->table(['Metric', 'Value'], [
            ['Locale', 'uk'],
            ['Sections', (string) count(self::TARGET_SECTIONS)],
            ['Total entries', (string) $totalEntries],
            ['Preserved manual UK', (string) $preservedUk],
            ['From translation cache', (string) $fromCache],
            ['Fallback/untranslated', (string) $fallbackEn],
            ['SHA-256', (string) hash_file('sha256', $target)],
        ]);

        return self::SUCCESS;
    }
}
