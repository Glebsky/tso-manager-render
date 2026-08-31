<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Lang\GameLangFileWriter;
use App\Services\Lang\LangImportException;
use App\Services\Lang\LangXmlParser;
use Illuminate\Console\Command;

class LangImportCommand extends Command
{
    private const array SUPPORTED_LOCALES = ['en', 'ru'];

    protected $signature = 'tso:lang:import
        {source : Path to the game lang XML export (e.g. en_lang.xml)}
        {--locale= : Target locale (en or ru)}
        {--lang-dir= : Override the base lang directory (used by tests)}';

    protected $description = 'Import game translations from a lang XML export into lang/<locale>/game.php';

    public function handle(LangXmlParser $parser, GameLangFileWriter $writer): int
    {
        $source = (string) $this->argument('source');
        $locale = strtolower((string) $this->option('locale'));

        if (! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $this->error('The --locale option is required and must be one of: '.implode(', ', self::SUPPORTED_LOCALES).'.');

            return self::FAILURE;
        }

        try {
            $result = $parser->parse($source);
        } catch (LangImportException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($result->sourceLocale !== '' && $result->sourceLocale !== $locale) {
            $this->error("Locale mismatch: the XML declares locale \"{$result->sourceLocale}\" but --locale={$locale} was requested.");

            return self::FAILURE;
        }

        if ($result->hasConflicts()) {
            $this->error('Import aborted: conflicting duplicate ids found (same id, different text). No files were modified.');

            foreach ($result->conflicts as $conflict) {
                $this->line("  - {$conflict}");
            }

            return self::FAILURE;
        }

        $baseDirectory = (string) ($this->option('lang-dir') ?: lang_path());

        try {
            $target = $writer->write($result, $locale, basename($source), $baseDirectory.'/'.$locale);
        } catch (LangImportException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$result->importedEntries} translations into {$target}");
        $this->table(['Metric', 'Value'], [
            ['Locale', $locale],
            ['Sections', (string) $result->sectionCount()],
            ['Imported entries', (string) $result->importedEntries],
            ['Deduplicated (same text)', (string) $result->deduplicatedEntries],
            ['Skipped (empty id)', (string) $result->skippedEmptyIds],
            ['Skipped (empty text)', (string) $result->skippedEmptyTexts],
            ['SHA-256', (string) hash_file('sha256', $target)],
        ]);

        return self::SUCCESS;
    }
}
