<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class LangImportCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/lang-import-test-'.uniqid();
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function fixture(string $name): string
    {
        return base_path('tests/Fixtures/Lang/'.$name);
    }

    public function test_imports_valid_xml_into_game_catalog(): void
    {
        $this->artisan('tso:lang:import', [
            'source' => $this->fixture('valid-lang.xml'),
            '--locale' => 'en',
            '--lang-dir' => $this->dir,
        ])->assertExitCode(0);

        $path = $this->dir.'/en/game.php';
        $this->assertFileExists($path);

        $catalog = require $path;
        $this->assertSame('Copper Ore', $catalog['RES']['BronzeOre']);
        $this->assertSame('General', $catalog['SPE']['General']);
    }

    public function test_rejects_locale_mismatch(): void
    {
        $this->artisan('tso:lang:import', [
            'source' => $this->fixture('valid-lang.xml'),
            '--locale' => 'ru',
            '--lang-dir' => $this->dir,
        ])->assertExitCode(1);

        $this->assertFileDoesNotExist($this->dir.'/ru/game.php');
    }

    public function test_rejects_unsupported_locale(): void
    {
        $this->artisan('tso:lang:import', [
            'source' => $this->fixture('valid-lang.xml'),
            '--locale' => 'de',
            '--lang-dir' => $this->dir,
        ])->assertExitCode(1);
    }

    public function test_aborts_on_conflicting_translations(): void
    {
        $this->artisan('tso:lang:import', [
            'source' => $this->fixture('conflict-lang.xml'),
            '--locale' => 'en',
            '--lang-dir' => $this->dir,
        ])->assertExitCode(1);

        $this->assertFileDoesNotExist($this->dir.'/en/game.php');
    }

    public function test_aborts_on_malformed_xml(): void
    {
        $this->artisan('tso:lang:import', [
            'source' => $this->fixture('malformed-lang.xml'),
            '--locale' => 'en',
            '--lang-dir' => $this->dir,
        ])->assertExitCode(1);
    }
}
