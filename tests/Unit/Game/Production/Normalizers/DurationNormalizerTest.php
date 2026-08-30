<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Production\Normalizers;

use App\Services\Game\Production\Normalizers\DurationNormalizer;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DurationNormalizerTest extends TestCase
{
    private DurationNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new DurationNormalizer;
    }

    public function test_extracts_duration_attribute(): void
    {
        $el = $this->createElement('<TimedProduction duration="180" />');
        $this->assertSame(180, $this->normalizer->fromElement($el));
    }

    public function test_extracts_production_time_attribute(): void
    {
        $el = $this->createElement('<Buff productionTime="3600" />');
        $this->assertSame(3600, $this->normalizer->fromElement($el));
    }

    public function test_extracts_production_time_seconds_attribute(): void
    {
        $el = $this->createElement('<MilitaryUnit productionTimeSeconds="720" />');
        $this->assertSame(720, $this->normalizer->fromElement($el));
    }

    public function test_extracts_nested_property_production_time(): void
    {
        $xml = <<<'XML'
<Unit Type="ExpeditionKnight">
    <Properties>
        <Property Type="ProductionTime" Value="360" />
    </Properties>
</Unit>
XML;
        $el = $this->createElement($xml);
        $this->assertSame(360, $this->normalizer->fromElement($el));
    }

    public function test_throws_exception_when_no_duration_present(): void
    {
        $el = $this->createElement('<UnknownElement name="Test" />');
        $this->expectException(InvalidArgumentException::class);
        $this->normalizer->fromElement($el);
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
