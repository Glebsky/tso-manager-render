<?php

declare(strict_types=1);

namespace App\Services\Lang;

use Illuminate\Translation\Translator;

/**
 * Resolves game translations from lang/<locale>/game.php by (section, id).
 *
 * - Exact-match lookup; ids are never treated as Laravel dot-keys.
 * - Locale fallback chain: <locale> -> en -> $fallback ?? raw id.
 * - Strict placeholder interpolation: only {N} and {N,SECTION} are
 *   interpolated. Any other curly-brace construct (e.g. {Christmas}) is
 *   preserved literally. Typed placeholders resolve their parameter value
 *   through the referenced section with a recursion depth limit and a
 *   cycle guard.
 */
final class GameTranslationResolver
{
    private const MAX_PLACEHOLDER_DEPTH = 3;

    /** @var array<string, array<string, array<string, string>>> */
    private array $catalogs = [];

    public function __construct(private readonly Translator $translator) {}

    /**
     * Resolve a plain display name. Returns $fallback (or the raw id) when
     * the translation is missing in the whole locale chain.
     */
    public function name(string $section, string $id, ?string $fallback = null, ?string $locale = null): string
    {
        return $this->resolve($section, $id, [], $fallback, $locale);
    }

    /**
     * Resolve a translation template and interpolate {N} / {N,SECTION} placeholders.
     *
     * @param  array<int, string|int|float>  $parameters  positional parameters, e.g. [0 => 500, 1 => 'BronzeOre']
     */
    public function resolve(string $section, string $id, array $parameters = [], ?string $fallback = null, ?string $locale = null): string
    {
        $template = $this->lookup($section, $id, $locale);

        if ($template === null) {
            return $fallback ?? $id;
        }

        return $this->interpolate($template, $parameters, $locale, 0, []);
    }

    public function has(string $section, string $id, ?string $locale = null): bool
    {
        return $this->lookup($section, $id, $locale) !== null;
    }

    private function lookup(string $section, string $id, ?string $locale): ?string
    {
        $lcFirst = lcfirst($id);
        $ucFirst = ucfirst($id);

        $cleanId = preg_replace('/[\s_]+/', '', $id) ?? $id;
        $cleanLcFirst = lcfirst($cleanId);
        $cleanUcFirst = ucfirst($cleanId);
        $cleanUcFirstLcRest = ucfirst(strtolower($cleanId));

        $candidates = array_values(array_unique([
            $id,
            $lcFirst,
            $ucFirst,
            $cleanId,
            $cleanLcFirst,
            $cleanUcFirst,
            $cleanUcFirstLcRest,
        ]));

        foreach ($this->localeChain($locale) as $chainLocale) {
            $catalog = $this->catalog($chainLocale);

            if (isset($catalog[$section])) {
                $sec = $catalog[$section];
                foreach ($candidates as $cand) {
                    if (isset($sec[$cand]) && is_string($sec[$cand])) {
                        return $sec[$cand];
                    }
                }

                $idLowerClean = str_replace([' ', '_'], '', strtolower($id));
                foreach ($sec as $k => $v) {
                    $kLowerClean = str_replace([' ', '_'], '', strtolower($k));
                    if ($kLowerClean === $idLowerClean && is_string($v)) {
                        return $v;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function localeChain(?string $locale): array
    {
        $locale = $locale ?? $this->translator->getLocale();

        return array_values(array_unique([$locale, 'en']));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function catalog(string $locale): array
    {
        if (! array_key_exists($locale, $this->catalogs)) {
            $lines = $this->translator->get('game', [], $locale, false);
            $this->catalogs[$locale] = is_array($lines) ? $lines : [];
        }

        return $this->catalogs[$locale];
    }

    /**
     * @param  array<int, string|int|float>  $parameters
     * @param  array<string, true>  $visited
     */
    private function interpolate(string $template, array $parameters, ?string $locale, int $depth, array $visited): string
    {
        return (string) preg_replace_callback(
            '/\{(\d+)(?:,([A-Za-z0-9_]+))?\}/',
            function (array $matches) use ($parameters, $locale, $depth, $visited): string {
                $index = (int) $matches[1];

                if (! array_key_exists($index, $parameters)) {
                    return $matches[0];
                }

                $value = (string) $parameters[$index];
                $section = $matches[2] ?? '';

                if ($section === '') {
                    return $value;
                }

                $visitKey = "{$section}/{$value}";

                if ($depth >= self::MAX_PLACEHOLDER_DEPTH || isset($visited[$visitKey])) {
                    return $value;
                }

                $nested = $this->lookup($section, $value, $locale);

                if ($nested === null) {
                    return $value;
                }

                $visited[$visitKey] = true;

                return $this->interpolate($nested, [], $locale, $depth + 1, $visited);
            },
            $template
        );
    }
}
