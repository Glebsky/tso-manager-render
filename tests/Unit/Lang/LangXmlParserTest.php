<?php

declare(strict_types=1);

namespace Tests\Unit\Lang;

use App\Services\Lang\LangImportException;
use App\Services\Lang\LangXmlParser;
use PHPUnit\Framework\TestCase;

class LangXmlParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return dirname(__DIR__, 2).'/Fixtures/Lang/'.$name;
    }

    public function test_parses_valid_file_with_stats(): void
    {
        $result = (new LangXmlParser)->parse($this->fixture('valid-lang.xml'));

        $this->assertSame('en', $result->sourceLocale);
        $this->assertSame(2, $result->sectionCount());
        $this->assertSame(5, $result->importedEntries);
        $this->assertSame(1, $result->deduplicatedEntries);
        $this->assertSame(1, $result->skippedEmptyIds);
        $this->assertSame(1, $result->skippedEmptyTexts);
        $this->assertFalse($result->hasConflicts());

        $this->assertSame('Copper Ore', $result->sections['RES']['BronzeOre']);
        $this->assertSame('Add Resource: {0} {1,RES}', $result->sections['RES']['AddResource']);
        $this->assertSame('Merry {Christmas}!', $result->sections['RES']['Christmas2015']);
        $this->assertSame('General', $result->sections['SPE']['General']);
        $this->assertArrayNotHasKey('EmptyText', $result->sections['RES']);
    }

    public function test_records_conflicting_duplicate_ids(): void
    {
        $result = (new LangXmlParser)->parse($this->fixture('conflict-lang.xml'));

        $this->assertTrue($result->hasConflicts());
        $this->assertNotEmpty($result->conflicts);
        // First occurrence wins until the conflict is resolved manually.
        $this->assertSame('Water', $result->sections['RES']['Water']);
    }

    public function test_malformed_xml_throws_import_exception(): void
    {
        $this->expectException(LangImportException::class);

        (new LangXmlParser)->parse($this->fixture('malformed-lang.xml'));
    }

    public function test_missing_file_throws_import_exception(): void
    {
        $this->expectException(LangImportException::class);

        (new LangXmlParser)->parse($this->fixture('does-not-exist.xml'));
    }

    public function test_normalizes_locale_aliases(): void
    {
        $parser = new LangXmlParser;

        $this->assertSame('en', $parser->normalizeLocale('en_uk'));
        $this->assertSame('ru', $parser->normalizeLocale('ru_ru'));
        $this->assertSame('en', $parser->normalizeLocale('EN'));
    }
}
