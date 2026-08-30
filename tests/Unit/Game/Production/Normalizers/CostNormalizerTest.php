<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Production\Normalizers;

use App\Services\Game\Production\Normalizers\CostNormalizer;
use DOMDocument;
use DOMElement;
use PHPUnit\Framework\TestCase;

final class CostNormalizerTest extends TestCase
{
    private CostNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new CostNormalizer;
    }

    public function test_from_costs_block_with_mixed_and_population(): void
    {
        $xml = <<<'XML'
<MilitaryUnit type="Bowman" produceable="true">
    <costs>
        <cost name="Beer" count="10" />
        <cost name="Population" count="1" />
        <cost name="Bow" count="10" />
    </costs>
</MilitaryUnit>
XML;
        $el = $this->createElement($xml);
        $costs = $this->normalizer->fromCostsBlock($el);

        $this->assertCount(3, $costs);
        $this->assertSame([
            ['resource' => 'Beer', 'count' => 10, 'is_population' => false],
            ['resource' => 'Bow', 'count' => 10, 'is_population' => false],
            ['resource' => 'Population', 'count' => 1, 'is_population' => true],
        ], $costs);
    }

    public function test_from_lowercase_costs(): void
    {
        $xml = <<<'XML'
<productionLevel amountProduced="0" productionTime="1800">
    <cost name="SimplePaper" count="250" />
    <cost name="Water" count="250" />
</productionLevel>
XML;
        $el = $this->createElement($xml);
        $costs = $this->normalizer->fromLowercaseCosts($el);

        $this->assertCount(2, $costs);
        $this->assertSame([
            ['resource' => 'SimplePaper', 'count' => 250, 'is_population' => false],
            ['resource' => 'Water', 'count' => 250, 'is_population' => false],
        ], $costs);
    }

    public function test_from_resources(): void
    {
        $xml = <<<'XML'
<collection name="RedNoseCollection">
    <resource name="CollectibleHerbs" amount="8" />
    <resource name="CollectibleScarecrow" amount="8" />
</collection>
XML;
        $el = $this->createElement($xml);
        $costs = $this->normalizer->fromResources($el);

        $this->assertCount(2, $costs);
        $this->assertSame([
            ['resource' => 'CollectibleHerbs', 'count' => 8, 'is_population' => false],
            ['resource' => 'CollectibleScarecrow', 'count' => 8, 'is_population' => false],
        ], $costs);
    }

    public function test_from_capitalized_costs_with_population_and_valor_points(): void
    {
        $xml = <<<'XML'
<Unit Type="ExpeditionTank">
    <Costs>
        <Cost Type="Population" Amount="1" />
        <Cost Type="ValorPoint" Amount="4" />
        <Cost Type="Beer" Amount="20" />
    </Costs>
</Unit>
XML;
        $el = $this->createElement($xml);
        $costs = $this->normalizer->fromCapitalizedCosts($el);

        $this->assertCount(3, $costs);
        $this->assertSame([
            ['resource' => 'Beer', 'count' => 20, 'is_population' => false],
            ['resource' => 'Population', 'count' => 1, 'is_population' => true],
            ['resource' => 'ValorPoint', 'count' => 4, 'is_population' => false],
        ], $costs);
    }

    private function createElement(string $xml): DOMElement
    {
        $doc = new DOMDocument;
        $doc->loadXML($xml);

        /** @var DOMElement $el */
        $el = $doc->documentElement;

        return $el;
    }
}
