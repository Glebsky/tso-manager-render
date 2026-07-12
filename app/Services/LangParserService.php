<?php

namespace App\Services;

class LangParserService
{
    private static ?array $resTranslations = null;

    public function getResTranslations(): array
    {
        if (self::$resTranslations !== null) {
            return self::$resTranslations;
        }

        $langPath = base_path('../lang.txt');
        if (!file_exists($langPath)) {
            return [];
        }

        $xml = simplexml_load_file($langPath);
        if ($xml === false) {
            return [];
        }

        $translations = [];

        foreach ($xml->translations as $translationsBlock) {
            foreach ($translationsBlock->s as $section) {
                $category = (string) $section['name'];
                if ($category !== 'RES') {
                    continue;
                }

                foreach ($section->t as $entry) {
                    $id = (string) $entry['id'];
                    $text = (string) $entry['text'];
                    if ($id && $text) {
                        $translations[$id] = $text;
                    }
                }

                break 2;
            }
        }

        self::$resTranslations = $translations;
        return $translations;
    }
}
