<?php

declare(strict_types=1);

namespace App\Services\Lang;

/**
 * Writes a parsed game translation catalog to lang/<locale>/game.php.
 *
 * The output is deterministic: sections and ids are sorted byte-wise
 * (SORT_STRING), so the same source XML always produces a byte-identical
 * file and stable git diffs. The file is written atomically (tmp + rename).
 */
final class GameLangFileWriter
{
    public function write(LangImportResult $result, string $locale, string $sourceFileName, string $directory): string
    {
        $sections = $result->sections;
        ksort($sections, SORT_STRING);

        $php = "<?php\n\ndeclare(strict_types=1);\n\n";
        $php .= "/*\n";
        $php .= " * DO NOT EDIT. Generated game translation catalog.\n";
        $php .= " * Source: {$sourceFileName}\n";
        $php .= " * Locale: {$locale}\n";
        $php .= " * Regenerate: php artisan tso:lang:import /path/to/{$sourceFileName} --locale={$locale}\n";
        $php .= " */\n\n";
        $php .= "return [\n";

        foreach ($sections as $section => $entries) {
            ksort($entries, SORT_STRING);
            $php .= '    '.$this->exportString((string) $section)." => [\n";

            foreach ($entries as $id => $text) {
                $php .= '        '.$this->exportString((string) $id).' => '.$this->exportString($text).",\n";
            }

            $php .= "    ],\n";
        }

        $php .= "];\n";

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new LangImportException("Unable to create lang directory: {$directory}");
        }

        $target = rtrim($directory, '/').'/game.php';
        $temporary = $target.'.tmp.'.getmypid();

        if (file_put_contents($temporary, $php) === false) {
            throw new LangImportException("Unable to write temporary lang file: {$temporary}");
        }

        if (! rename($temporary, $target)) {
            @unlink($temporary);

            throw new LangImportException("Unable to move generated lang file into place: {$target}");
        }

        return $target;
    }

    private function exportString(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }
}
