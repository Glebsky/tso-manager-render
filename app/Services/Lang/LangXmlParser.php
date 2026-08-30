<?php

declare(strict_types=1);

namespace App\Services\Lang;

use XMLReader;

/**
 * Streaming parser for The Settlers Online lang XML exports.
 *
 * Expected structure:
 * <oasis>
 *   <translations language="en_uk">
 *     <s name="RES">
 *       <t id="Water" text="Water"/>
 *     </s>
 *   </translations>
 * </oasis>
 *
 * Uses XMLReader (streaming) so multi-megabyte exports do not blow up memory.
 * External entity loading is disabled (LIBXML_NONET, no LIBXML_NOENT).
 */
final class LangXmlParser
{
    private const array LOCALE_ALIASES = [
        'en_uk' => 'en',
        'ru_ru' => 'ru',
    ];

    public function parse(string $path): LangImportResult
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new LangImportException("Lang XML file is not readable: {$path}");
        }

        $reader = XMLReader::open($path, null, LIBXML_NONET | LIBXML_COMPACT);

        if (! $reader instanceof XMLReader) {
            throw new LangImportException("Unable to open lang XML file: {$path}");
        }

        $sourceLocale = '';
        $currentSection = null;
        $sections = [];
        $imported = 0;
        $skippedEmptyIds = 0;
        $skippedEmptyTexts = 0;
        $deduplicated = 0;
        $conflicts = [];

        $previousErrorHandling = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT) {
                    continue;
                }

                switch ($reader->localName) {
                    case 'translations':
                        $sourceLocale = $this->normalizeLocale((string) $reader->getAttribute('language'));
                        break;
                    case 's':
                        $currentSection = (string) $reader->getAttribute('name');
                        break;
                    case 't':
                        if ($currentSection === null || $currentSection === '') {
                            break;
                        }

                        $id = (string) $reader->getAttribute('id');
                        $text = (string) $reader->getAttribute('text');

                        if ($id === '') {
                            $skippedEmptyIds++;
                            break;
                        }

                        if ($text === '') {
                            $skippedEmptyTexts++;
                            break;
                        }

                        if (isset($sections[$currentSection][$id])) {
                            if ($sections[$currentSection][$id] === $text) {
                                $deduplicated++;
                            } else {
                                $conflicts[] = "{$currentSection}/{$id}";
                            }
                            break;
                        }

                        $sections[$currentSection][$id] = $text;
                        $imported++;
                        break;
                }
            }

            $xmlErrors = libxml_get_errors();
            libxml_clear_errors();

            if ($xmlErrors !== []) {
                $first = $xmlErrors[0];

                throw new LangImportException(
                    'Malformed lang XML: '.trim($first->message).' (line '.$first->line.')'
                );
            }
        } finally {
            libxml_use_internal_errors($previousErrorHandling);
            $reader->close();
        }

        if ($sections === []) {
            throw new LangImportException('Lang XML does not contain any <s>/<t> translation entries.');
        }

        return new LangImportResult(
            sourceLocale: $sourceLocale,
            sections: $sections,
            importedEntries: $imported,
            skippedEmptyIds: $skippedEmptyIds,
            skippedEmptyTexts: $skippedEmptyTexts,
            deduplicatedEntries: $deduplicated,
            conflicts: array_values(array_unique($conflicts)),
        );
    }

    public function normalizeLocale(string $rawLocale): string
    {
        $normalized = strtolower(trim($rawLocale));

        if (isset(self::LOCALE_ALIASES[$normalized])) {
            return self::LOCALE_ALIASES[$normalized];
        }

        $primary = explode('_', $normalized, 2)[0];

        return $primary !== '' ? $primary : $normalized;
    }
}
