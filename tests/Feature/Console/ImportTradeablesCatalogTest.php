<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportTradeablesCatalogTest extends TestCase
{
    private string $tempConfigFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempConfigFile = base_path('tests/Fixtures/temp_game_tradeables.php');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tempConfigFile)) {
            File::delete($this->tempConfigFile);
        }
        parent::tearDown();
    }

    public function test_import_command_runs_successfully(): void
    {
        $status = Artisan::call('tso:import-tradeables-catalog', [
            '--output' => $this->tempConfigFile,
        ]);
        $this->assertSame(0, $status);

        $this->assertFileExists($this->tempConfigFile);

        /** @var array<string, mixed> $catalog */
        $catalog = require $this->tempConfigFile;

        $this->assertArrayHasKey('adventures', $catalog);
        $this->assertArrayHasKey('buffs', $catalog);

        $this->assertArrayHasKey('MadHenry', $catalog['adventures']);
        $this->assertTrue($catalog['adventures']['MadHenry']['tradable']);
        $this->assertSame(8, $catalog['adventures']['MadHenry']['difficulty']);

        $this->assertArrayHasKey('FillDeposit_Fishfood', $catalog['buffs']);
        $this->assertFalse($catalog['buffs']['FillDeposit_Fishfood']['tradable']);
        $this->assertSame('Fish', $catalog['buffs']['FillDeposit_Fishfood']['resource']);
    }

    public function test_dry_run_does_not_write_file(): void
    {
        $status = Artisan::call('tso:import-tradeables-catalog', [
            '--output' => $this->tempConfigFile,
            '--dry-run' => true,
        ]);
        $this->assertSame(0, $status);

        $this->assertFileDoesNotExist($this->tempConfigFile);
    }

    public function test_output_is_sorted_and_idempotent(): void
    {
        $statusFirst = Artisan::call('tso:import-tradeables-catalog', [
            '--output' => $this->tempConfigFile,
        ]);
        $this->assertSame(0, $statusFirst);

        $contentFirst = File::get($this->tempConfigFile);

        $statusSecond = Artisan::call('tso:import-tradeables-catalog', [
            '--output' => $this->tempConfigFile,
        ]);
        $this->assertSame(0, $statusSecond);

        $contentSecond = File::get($this->tempConfigFile);

        $this->assertSame($contentFirst, $contentSecond);
    }
}
