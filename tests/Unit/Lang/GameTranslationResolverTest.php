<?php

declare(strict_types=1);

namespace Tests\Unit\Lang;

use App\Services\Lang\GameTranslationResolver;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use PHPUnit\Framework\TestCase;

class GameTranslationResolverTest extends TestCase
{
    private function makeResolver(array $en, array $ru = [], string $locale = 'en'): GameTranslationResolver
    {
        $loader = new ArrayLoader;
        $loader->addMessages('en', 'game', $en);
        $loader->addMessages('ru', 'game', $ru);

        $translator = new Translator($loader, $locale);
        $translator->setFallback('en');

        return new GameTranslationResolver($translator);
    }

    public function test_returns_exact_translation(): void
    {
        $resolver = $this->makeResolver(['RES' => ['Water' => 'Water']]);

        $this->assertSame('Water', $resolver->name('RES', 'Water'));
        $this->assertTrue($resolver->has('RES', 'Water'));
    }

    public function test_resolves_normalized_resource_names_with_spaces_and_casing_variations(): void
    {
        $resolver = $this->makeResolver([
            'RES' => [
                'Mahoganyplank' => 'Доски из махагониевого дерева',
                'Platinumsword' => 'Платиновый меч',
            ],
        ]);

        $this->assertSame('Доски из махагониевого дерева', $resolver->name('RES', 'Mahogany Plank'));
        $this->assertSame('Доски из махагониевого дерева', $resolver->name('RES', 'MahoganyPlank'));
        $this->assertSame('Платиновый меч', $resolver->name('RES', 'Platinum Sword'));
        $this->assertSame('Платиновый меч', $resolver->name('RES', 'PlatinumSword'));
    }

    public function test_missing_id_falls_back_to_fallback_then_id(): void
    {
        $resolver = $this->makeResolver(['RES' => []]);

        $this->assertSame('Legacy Name', $resolver->name('RES', 'Unknown_Id', 'Legacy Name'));
        $this->assertSame('Unknown_Id', $resolver->name('RES', 'Unknown_Id'));
        $this->assertFalse($resolver->has('RES', 'Unknown_Id'));
    }

    public function test_ru_locale_falls_back_to_english(): void
    {
        $resolver = $this->makeResolver(
            en: ['RES' => ['Water' => 'Water', 'BronzeOre' => 'Copper Ore']],
            ru: ['RES' => ['Water' => 'Вода']],
            locale: 'ru',
        );

        $this->assertSame('Вода', $resolver->name('RES', 'Water'));
        $this->assertSame('Copper Ore', $resolver->name('RES', 'BronzeOre'));
    }

    public function test_resolves_numeric_and_section_placeholders(): void
    {
        $resolver = $this->makeResolver([
            'RES' => [
                'AddResource' => 'Add Resource: {0} {1,RES}',
                'BronzeOre' => 'Copper Ore',
            ],
        ]);

        $this->assertSame(
            'Add Resource: 500 Copper Ore',
            $resolver->resolve('RES', 'AddResource', ['500', 'BronzeOre']),
        );
    }

    public function test_non_numeric_placeholders_are_preserved(): void
    {
        $resolver = $this->makeResolver(['RES' => ['Christmas2015' => 'Merry {Christmas}!']]);

        $this->assertSame('Merry {Christmas}!', $resolver->resolve('RES', 'Christmas2015', ['ignored']));
    }

    public function test_missing_parameters_stay_literal(): void
    {
        $resolver = $this->makeResolver(['RES' => ['Pair' => '{0} x {1}']]);

        $this->assertSame('A x {1}', $resolver->resolve('RES', 'Pair', ['A']));
    }

    public function test_nested_placeholder_resolution_is_depth_limited(): void
    {
        $resolver = $this->makeResolver([
            'RES' => [
                'Wrap' => 'Deed: {0,RES}',
                'Inner' => 'Leaf',
            ],
        ]);

        $this->assertSame('Deed: Leaf', $resolver->resolve('RES', 'Wrap', ['Inner']));
    }
}
