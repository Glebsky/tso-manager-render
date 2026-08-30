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

    public function test_all_php_lang_files_have_identical_key_structures(): void
    {
        $domains = ['ui', 'tasks', 'logs', 'auth', 'validation'];
        $langDir = __DIR__.'/../../../lang';

        foreach ($domains as $domain) {
            $enFile = "{$langDir}/en/{$domain}.php";
            $ruFile = "{$langDir}/ru/{$domain}.php";
            $ukFile = "{$langDir}/uk/{$domain}.php";

            $this->assertFileExists($enFile);
            $this->assertFileExists($ruFile);
            $this->assertFileExists($ukFile);

            $enData = require $enFile;
            $ruData = require $ruFile;
            $ukData = require $ukFile;

            $enKeys = $this->extractKeys($enData);
            $ruKeys = $this->extractKeys($ruData);
            $ukKeys = $this->extractKeys($ukData);

            sort($enKeys);
            sort($ruKeys);
            sort($ukKeys);

            $missingInRu = array_diff($enKeys, $ruKeys);
            $missingInUk = array_diff($enKeys, $ukKeys);
            $extraInRu = array_diff($ruKeys, $enKeys);
            $extraInUk = array_diff($ukKeys, $enKeys);

            $this->assertEmpty($missingInRu, "Keys present in en/{$domain}.php but missing in ru/{$domain}.php: ".implode(', ', $missingInRu));
            $this->assertEmpty($missingInUk, "Keys present in en/{$domain}.php but missing in uk/{$domain}.php: ".implode(', ', $missingInUk));
            $this->assertEmpty($extraInRu, "Keys present in ru/{$domain}.php but missing in en/{$domain}.php: ".implode(', ', $extraInRu));
            $this->assertEmpty($extraInUk, "Keys present in uk/{$domain}.php but missing in en/{$domain}.php: ".implode(', ', $extraInUk));
        }
    }

    public function test_critical_keys_exist_in_all_locales(): void
    {
        $criticalKeys = [
            'common.change',
            'tasks.max_level_target',
            'tasks.free_or_unknown_cost',
            'tasks.toast.series_scheduled',
        ];

        $langDir = __DIR__.'/../../../resources/js/lang/generated';
        foreach (['en', 'ru', 'uk'] as $locale) {
            $data = json_decode((string) file_get_contents("{$langDir}/{$locale}.json"), true);
            $uiKeys = $data['ui'] ?? [];
            foreach ($criticalKeys as $key) {
                $this->assertArrayHasKey($key, $uiKeys, "Critical key '{$key}' missing in {$locale}.json");
            }
        }
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
