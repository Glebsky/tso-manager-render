<?php

declare(strict_types=1);

namespace Tests\Unit\Lang;

use PHPUnit\Framework\TestCase;

class LocaleKeysParityTest extends TestCase
{
    public function test_all_locale_json_files_have_identical_key_structures(): void
    {
        $langDir = __DIR__.'/../../../resources/js/lang/generated';

        $enFile = $langDir.'/en.json';
        $ruFile = $langDir.'/ru.json';
        $ukFile = $langDir.'/uk.json';

        $this->assertFileExists($enFile);
        $this->assertFileExists($ruFile);
        $this->assertFileExists($ukFile);

        $enData = json_decode((string) file_get_contents($enFile), true);
        $ruData = json_decode((string) file_get_contents($ruFile), true);
        $ukData = json_decode((string) file_get_contents($ukFile), true);

        $enKeys = array_merge(
            $this->extractKeys($enData['ui'] ?? [], 'ui'),
            $this->extractKeys($enData['tasks'] ?? [], 'tasks')
        );
        $ruKeys = array_merge(
            $this->extractKeys($ruData['ui'] ?? [], 'ui'),
            $this->extractKeys($ruData['tasks'] ?? [], 'tasks')
        );
        $ukKeys = array_merge(
            $this->extractKeys($ukData['ui'] ?? [], 'ui'),
            $this->extractKeys($ukData['tasks'] ?? [], 'tasks')
        );

        sort($enKeys);
        sort($ruKeys);
        sort($ukKeys);

        $missingInRu = array_diff($enKeys, $ruKeys);
        $missingInUk = array_diff($enKeys, $ukKeys);

        $this->assertEmpty($missingInRu, 'Keys present in en.json but missing in ru.json: '.implode(', ', $missingInRu));
        $this->assertEmpty($missingInUk, 'Keys present in en.json but missing in uk.json: '.implode(', ', $missingInUk));
    }

    /**
     * @param  array<string, mixed>  $array
     * @return list<string>
     */
    private function extractKeys(array $array, string $prefix = ''): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $keys = array_merge($keys, $this->extractKeys($value, $fullKey));
            } else {
                $keys[] = $fullKey;
            }
        }

        return $keys;
    }
}
