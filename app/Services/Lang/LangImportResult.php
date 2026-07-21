<?php

declare(strict_types=1);

namespace App\Services\Lang;

/**
 * Immutable result of parsing a game lang XML export.
 */
final class LangImportResult
{
    /**
     * @param  array<string, array<string, string>>  $sections  section => [id => text]
     * @param  list<string>  $conflicts  "SECTION/id" keys that appeared twice with different texts
     */
    public function __construct(
        public readonly string $sourceLocale,
        public readonly array $sections,
        public readonly int $importedEntries,
        public readonly int $skippedEmptyIds,
        public readonly int $skippedEmptyTexts,
        public readonly int $deduplicatedEntries,
        public readonly array $conflicts,
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
