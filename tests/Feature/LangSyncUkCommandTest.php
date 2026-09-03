<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LangSyncUkCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/lang-sync-uk-test-'.uniqid();
        mkdir($this->dir.'/ru', 0777, true);
        mkdir($this->dir.'/en', 0777, true);
        mkdir($this->dir.'/uk', 0777, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_syncs_ukrainian_catalog_with_preservation_and_cache(): void
    {
        file_put_contents($this->dir.'/ru/game.php', "<?php\nreturn [\n    'RES' => ['Beer' => 'Квас', 'Bread' => 'Хлеб', 'NewBuff' => 'Новый баф'],\n];\n");
        file_put_contents($this->dir.'/en/game.php', "<?php\nreturn [\n    'RES' => ['Beer' => 'Brew', 'Bread' => 'Bread', 'NewBuff' => 'New Buff'],\n];\n");
        file_put_contents($this->dir.'/uk/game.php', "<?php\nreturn [\n    'RES' => ['Beer' => 'Квас (ручний)'],\n];\n");

        $cacheFile = $this->dir.'/cache.json';
        file_put_contents($cacheFile, json_encode([
            'Хлеб' => 'Хліб',
            'Новый баф' => 'Новий баф',
        ], JSON_THROW_ON_ERROR));

        $this->assertSame(0, Artisan::call('tso:lang:sync-uk', [
            '--lang-dir' => $this->dir,
            '--cache-file' => $cacheFile,
        ]));

        $path = $this->dir.'/uk/game.php';
        $this->assertFileExists($path);

        $catalog = require $path;
        $this->assertSame('Квас (ручний)', $catalog['RES']['Beer']);
        $this->assertSame('Хліб', $catalog['RES']['Bread']);
        $this->assertSame('Новий баф', $catalog['RES']['NewBuff']);
    }
}
