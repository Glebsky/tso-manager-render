<?php

declare(strict_types=1);

namespace App\Services\Lang;

/**
 * Immutable result of parsing a game lang XML export.
 */
final readonly class LangImportResult
{
    /**
     * @param  array<string, array<string, string>>  $sections  section => [id => text]
     * @param  list<string>  $conflicts  "SECTION/id" keys that appeared twice with different texts
     */
    public function __construct(
        public string $sourceLocale,
        public array $sections,
        public int $importedEntries,
        public int $skippedEmptyIds,
        public int $skippedEmptyTexts,
        public int $deduplicatedEntries,
        public array $conflicts,
    ) {}

    public function sectionCount(): int
    {
        return count($this->sections);
    }

    public function hasConflicts(): bool
    {
        return $this->conflicts !== [];
    }
}
