<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Production\Normalizers;

use App\Services\Game\Production\Normalizers\InstantFinishCostNormalizer;
use DOMDocument;
use DOMElement;
use PHPUnit\Framework\TestCase;

final class InstantFinishCostNormalizerTest extends TestCase
{
    private InstantFinishCostNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new InstantFinishCostNormalizer;
    }

    public function test_extracts_instant_finish_cost_attribute(): void
    {
        $el = $this->createElement('<skillPoint id="Manuscript" instantFinishCost="200" />');
        $this->assertSame(200, $this->normalizer->fromElement($el));
    }

    public function test_extracts_instant_build_costs_lowercase_attribute(): void
    {
        $el = $this->createElement('<Buff name="TestBuff" instantBuildCosts="45" />');
        $this->assertSame(45, $this->normalizer->fromElement($el));
    }

    public function test_extracts_instant_build_costs_capitalized_attribute(): void
    {
        $el = $this->createElement('<collection name="TestCol" InstantBuildCosts="30" />');
        $this->assertSame(30, $this->normalizer->fromElement($el));
    }

    public function test_extracts_nested_property_instant_build_cost(): void
    {
        $xml = <<<'XML'
<Unit Type="ExpeditionPikeman">
    <Properties>
        <Property Type="InstantBuildCost" Value="2" />
    </Properties>
</Unit>
XML;
        $el = $this->createElement($xml);
        $this->assertSame(2, $this->normalizer->fromElement($el));
    }

    public function test_returns_null_when_missing(): void
    {
        $el = $this->createElement('<Buff name="FreeBuff" />');
        $this->assertNull($this->normalizer->fromElement($el));
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
