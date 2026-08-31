<?php

declare(strict_types=1);

namespace App\Services\Game\Production;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class GameXmlLocator
{
    public const string ROLE_GLOBALS = 'globals';

    public const string ROLE_ICONS = 'icons';

    public const string ROLE_SKILLPOINTS = 'skillpoints';

    public const string ROLE_COLLECTIONS = 'collections';

    public const string ROLE_UNITS = 'units';

    public const array ALL_ROLES = [
        self::ROLE_GLOBALS,
        self::ROLE_ICONS,
        self::ROLE_SKILLPOINTS,
        self::ROLE_COLLECTIONS,
        self::ROLE_UNITS,
    ];

    private const array ROLE_SUBDIRECTORIES = [
        self::ROLE_GLOBALS => '/gfx/',
        self::ROLE_ICONS => '/gfx/',
        self::ROLE_SKILLPOINTS => '/skill/',
        self::ROLE_COLLECTIONS => '/collections/',
        self::ROLE_UNITS => '/settings/',
    ];

    private const array ROLE_SIGNATURES = [
        self::ROLE_GLOBALS => ['<TimedProductionList'],
        self::ROLE_ICONS => ['productionType="'],
        self::ROLE_SKILLPOINTS => ['<skillPoint ', '<skillPoints>'],
        self::ROLE_COLLECTIONS => ['<collections>', '<collection '],
        self::ROLE_UNITS => ['<GameUnit', '<UnitData>'],
    ];

    /**
     * Locate all 5 XML files.
     *
     * @param  array<string, string|null>  $explicitPaths  role => explicit path
     * @return array<string, string> role => resolved absolute or relative path
     */
    public function locateAll(string $baseDir = 'docs/references/xml', array $explicitPaths = []): array
    {
        $resolved = [];
        $missingRoles = [];

        foreach (self::ALL_ROLES as $role) {
            $explicit = $explicitPaths[$role] ?? null;
            if ($explicit !== null && $explicit !== '') {
                if (! file_exists($explicit)) {
                    throw new RuntimeException("Explicit XML file for role '{$role}' not found: {$explicit}");
                }
                $resolved[$role] = $explicit;
            } else {
                $missingRoles[] = $role;
            }
        }

        if ($missingRoles === []) {
            return $resolved;
        }

        if (! is_dir($baseDir)) {
            throw new RuntimeException("Base directory for XML references not found: {$baseDir}");
        }

        $files = $this->scanDirectoryForXmlFiles($baseDir);
        $hasSubdirs = $this->hasSubdirectories($baseDir);

        $candidatesByRole = [];
        foreach ($missingRoles as $role) {
            $candidatesByRole[$role] = [];
        }

        foreach ($files as $file) {
            $contentSample = (string) file_get_contents($file, false, null, 0, 16384);
            $fullContent = null; // Lazy load if needed

            foreach ($missingRoles as $role) {
                if ($this->matchesRole($role, $file, $contentSample, $fullContent, $hasSubdirs)) {
                    $candidatesByRole[$role][] = $file;
                }
            }
        }

        foreach ($missingRoles as $role) {
            $candidates = $candidatesByRole[$role] ?? [];
            $count = count($candidates);
            if ($count === 0) {
                throw new RuntimeException(
                    "Could not auto-detect XML file for role '{$role}' in '{$baseDir}'. "
                    .'Please specify the path explicitly via command line options.'
                );
            }

            if ($count > 1) {
                $fileList = implode(', ', $candidates);
                throw new RuntimeException(
                    "Ambiguous XML files found for role '{$role}' in '{$baseDir}' ({$count} candidates: {$fileList}). "
                    .'Please specify the path explicitly via command line options.'
                );
            }

            $resolved[$role] = $candidates[0];
        }

        return $resolved;
    }

    private function matchesRole(string $role, string $file, string $contentSample, ?string &$fullContent, bool $requireSubdirs): bool
    {
        $normalizedPath = str_replace('\\', '/', $file);

        if ($requireSubdirs) {
            $expectedSubdir = self::ROLE_SUBDIRECTORIES[$role] ?? null;
            if ($expectedSubdir !== null && ! str_contains($normalizedPath, $expectedSubdir)) {
                return false;
            }
        }

        $signatures = self::ROLE_SIGNATURES[$role] ?? [];
        foreach ($signatures as $signature) {
            if (str_contains($contentSample, $signature) || $this->searchFullContent($file, $signature, $fullContent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param-out string $fullContent
     */
    private function searchFullContent(string $file, string $needle, ?string &$fullContent): bool
    {
        if ($fullContent === null) {
            $fullContent = (string) file_get_contents($file);
        }

        return str_contains($fullContent, $needle);
    }

    /**
     * @return list<string>
     */
    private function scanDirectoryForXmlFiles(string $dir): array
    {
        $xmlFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                $xmlFiles[] = $file->getPathname();
            }
        }

        return $xmlFiles;
    }

    private function hasSubdirectories(string $dir): bool
    {
        $entries = scandir($dir);
        if ($entries === false) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..' && is_dir($dir.'/'.$entry)) {
                return true;
            }
        }

        return false;
    }
}
