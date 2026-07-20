<?php

declare(strict_types=1);

namespace Tests\Unit\Lang;

use App\Services\Lang\GameLangFileWriter;
use App\Services\Lang\LangImportResult;
use PHPUnit\Framework\TestCase;

class GameLangFileWriterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/lang-writer-test-'.uniqid();
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function result(): LangImportResult
    {
        return new LangImportResult(
            sourceLocale: 'en',
            sections: [
                'SPE' => ['General' => 'General'],
                'RES' => [
                    'Water' => "O'Brien's \\ water",
                    'BronzeOre' => 'Copper Ore',
                ],
            ],
            importedEntries: 3,
            skippedEmptyIds: 0,
            skippedEmptyTexts: 0,
            deduplicatedEntries: 0,
            conflicts: [],
        );
    }

    public function test_writes_loadable_catalog_with_sorted_keys(): void
    {
        $path = (new GameLangFileWriter())->write($this->result(), 'en', 'en_lang.xml', $this->dir);

        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertStringContainsString('DO NOT EDIT', $content);
        $this->assertStringContainsString('declare(strict_types=1);', $content);

        $catalog = require $path;
        $this->assertSame(['RES', 'SPE'], array_keys($catalog));
        $this->assertSame(['BronzeOre', 'Water'], array_keys($catalog['RES']));
        $this->assertSame("O'Brien's \\ water", $catalog['RES']['Water']);
    }

    public function test_output_is_deterministic(): void
    {
        $writer = new GameLangFileWriter();

        $first = file_get_contents($writer->write($this->result(), 'en', 'en_lang.xml', $this->dir));
        $second = file_get_contents($writer->write($this->result(), 'en', 'en_lang.xml', $this->dir));

        $this->assertSame($first, $second);
    }
}
