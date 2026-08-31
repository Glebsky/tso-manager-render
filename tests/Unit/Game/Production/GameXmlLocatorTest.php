<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Production;

use App\Services\Game\Production\GameXmlLocator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GameXmlLocatorTest extends TestCase
{
    private GameXmlLocator $locator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->locator = new GameXmlLocator;
    }

    public function test_it_locates_all_five_xml_files_in_references_xml_directory(): void
    {
        $baseDir = dirname(__DIR__, 4).'/docs/references/xml';
        if (! is_dir($baseDir)) {
            $this->markTestSkipped("References XML dir not found at {$baseDir}");
        }

        $files = $this->locator->locateAll($baseDir);

        $this->assertCount(5, $files);
        $this->assertArrayHasKey(GameXmlLocator::ROLE_GLOBALS, $files);
        $this->assertArrayHasKey(GameXmlLocator::ROLE_ICONS, $files);
        $this->assertArrayHasKey(GameXmlLocator::ROLE_SKILLPOINTS, $files);
        $this->assertArrayHasKey(GameXmlLocator::ROLE_COLLECTIONS, $files);
        $this->assertArrayHasKey(GameXmlLocator::ROLE_UNITS, $files);

        foreach ($files as $role => $path) {
            $this->assertFileExists($path, "File for role '{$role}' should exist");
        }
    }

    public function test_it_respects_explicit_paths_override(): void
    {
        $tempDir = sys_get_temp_dir().'/locator_test_'.uniqid('', true);
        mkdir($tempDir, 0777, true);
        $dummyIcons = $tempDir.'/my_custom_icons.xml';
        file_put_contents($dummyIcons, '<Root><Building name="Test" productionType="1" /></Root>');

        $baseDir = dirname(__DIR__, 4).'/docs/references/xml';

        $files = $this->locator->locateAll($baseDir, [
            GameXmlLocator::ROLE_ICONS => $dummyIcons,
        ]);

        $this->assertSame($dummyIcons, $files[GameXmlLocator::ROLE_ICONS]);

        unlink($dummyIcons);
        rmdir($tempDir);
    }

    public function test_it_throws_when_explicit_file_does_not_exist(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Explicit XML file for role 'icons' not found");

        $this->locator->locateAll('docs/references/xml', [
            GameXmlLocator::ROLE_ICONS => 'non_existent_file.xml',
        ]);
    }

    public function test_it_throws_when_required_role_is_missing_in_empty_dir(): void
    {
        $tempDir = sys_get_temp_dir().'/empty_locator_test_'.uniqid('', true);
        mkdir($tempDir, 0777, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not auto-detect XML file for role');

        try {
            $this->locator->locateAll($tempDir);
        } finally {
            rmdir($tempDir);
        }
    }

    public function test_it_throws_when_ambiguous_candidates_found(): void
    {
        $tempDir = sys_get_temp_dir().'/ambiguous_locator_test_'.uniqid('', true);
        mkdir($tempDir.'/gfx', 0777, true);
        mkdir($tempDir.'/skill', 0777, true);
        mkdir($tempDir.'/collections', 0777, true);
        mkdir($tempDir.'/settings', 0777, true);

        file_put_contents($tempDir.'/gfx/file1.xml', '<Root><TimedProductionList id="1"/></Root>');
        file_put_contents($tempDir.'/gfx/file2.xml', '<Root><TimedProductionList id="2"/></Root>');
        file_put_contents($tempDir.'/gfx/icons.xml', '<Root><Building productionType="1"/></Root>');
        file_put_contents($tempDir.'/skill/skills.xml', '<scienceSystem><skillPoint id="Tome"/></scienceSystem>');
        file_put_contents($tempDir.'/collections/loot.xml', '<root><collections><collection name="C1"/></collections></root>');
        file_put_contents($tempDir.'/settings/game_units.xml', '<GameUnit><UnitData/></GameUnit>');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Ambiguous XML files found for role 'globals'");

        try {
            $this->locator->locateAll($tempDir);
        } finally {
            unlink($tempDir.'/gfx/file1.xml');
            unlink($tempDir.'/gfx/file2.xml');
            unlink($tempDir.'/gfx/icons.xml');
            unlink($tempDir.'/skill/skills.xml');
            unlink($tempDir.'/collections/loot.xml');
            unlink($tempDir.'/settings/game_units.xml');
            rmdir($tempDir.'/gfx');
            rmdir($tempDir.'/skill');
            rmdir($tempDir.'/collections');
            rmdir($tempDir.'/settings');
            rmdir($tempDir);
        }
    }

    public function test_no_hashed_xml_filenames_or_game_units_hardcoded_in_app_and_config(): void
    {
        $appDir = dirname(__DIR__, 4).'/app';
        $configDir = dirname(__DIR__, 4).'/config';

        $patterns = ['gfx_settings_', 'game_units.xml'];

        foreach ([$appDir, $configDir] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            /** @var \SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $content = (string) file_get_contents($file->getPathname());
                    foreach ($patterns as $pattern) {
                        $this->assertStringNotContainsString(
                            $pattern,
                            $content,
                            "Hardcoded '{$pattern}' found in {$file->getPathname()} (P-43 / FR-27 violation)"
                        );
                    }
                }
            }
        }
    }
}
