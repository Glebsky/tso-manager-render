<?php

declare(strict_types=1);

namespace App\Services\Game\Production\Normalizers;

use DOMElement;
use DOMNode;
use SimpleXMLElement;

final class CostNormalizer
{
    /**
     * Parse <Costs><Cost name="..." count="..." /></Costs>
     *
     * @return list<array{resource: string, count: int, is_population: bool}>
     */
    public function fromCostsBlock(DOMElement|SimpleXMLElement $parent): array
    {
        return $this->extract(
            parent: $parent,
            containerTag: 'costs',
            itemTag: 'cost',
            nameAttrs: ['name', 'Name'],
            countAttrs: ['count', 'Count']
        );
    }

    /**
     * Parse <cost name="..." count="..." /> in skillpoints.
     *
     * @return list<array{resource: string, count: int, is_population: bool}>
     */
    public function fromLowercaseCosts(DOMElement|SimpleXMLElement $parent): array
    {
        return $this->extract(
            parent: $parent,
            containerTag: null,
            itemTag: 'cost',
            nameAttrs: ['name', 'Name'],
            countAttrs: ['count', 'Count']
        );
    }

    /**
     * Parse <resource name="..." amount="..." /> in collections.
     *
     * @return list<array{resource: string, count: int, is_population: bool}>
     */
    public function fromResources(DOMElement|SimpleXMLElement $parent): array
    {
        return $this->extract(
            parent: $parent,
            containerTag: null,
            itemTag: 'resource',
            nameAttrs: ['name', 'Name'],
            countAttrs: ['amount', 'Amount']
        );
    }

    /**
     * Parse <Cost Type="..." Amount="..." /> in Combat 3 units.
     *
     * @return list<array{resource: string, count: int, is_population: bool}>
     */
    public function fromCapitalizedCosts(DOMElement|SimpleXMLElement $parent): array
    {
        return $this->extract(
            parent: $parent,
            containerTag: 'costs',
            itemTag: 'cost',
            nameAttrs: ['Type', 'type', 'name', 'Name'],
            countAttrs: ['Amount', 'amount', 'count', 'Count']
        );
    }

    /**
     * Generic, robust cost extractor supporting both DOMElement and SimpleXMLElement.
     *
     * @param  list<string>  $nameAttrs
     * @param  list<string>  $countAttrs
     * @return list<array{resource: string, count: int, is_population: bool}>
     */
    private function extract(
        DOMElement|SimpleXMLElement $parent,
        ?string $containerTag,
        string $itemTag,
        array $nameAttrs,
        array $countAttrs
    ): array {
        $costs = [];

        if ($parent instanceof DOMElement) {
            $targetNodes = [];

            if ($containerTag !== null) {
                $container = $this->findChildByTagName($parent, $containerTag);
                if ($container !== null) {
                    $targetNodes = $this->findChildrenByTagName($container, $itemTag);
                }
            } else {
                $targetNodes = $this->findChildrenByTagName($parent, $itemTag);
            }

            foreach ($targetNodes as $node) {
                $name = $this->getAttributeAny($node, $nameAttrs);
                $count = (int) $this->getAttributeAny($node, $countAttrs);

                if ($name !== '') {
                    $costs[] = [
                        'resource' => $name,
                        'count' => $count,
                        'is_population' => $name === 'Population',
                    ];
                }
            }
        } else {
            // SimpleXMLElement support
            $items = $this->findSimpleXmlItems($parent, $containerTag, $itemTag);
            foreach ($items as $item) {
                $name = '';
                foreach ($nameAttrs as $attr) {
                    if (isset($item[$attr])) {
                        $name = (string) $item[$attr];
                        break;
                    }
                }

                $count = 0;
                foreach ($countAttrs as $attr) {
                    if (isset($item[$attr])) {
                        $count = (int) $item[$attr];
                        break;
                    }
                }

                if ($name !== '') {
                    $costs[] = [
                        'resource' => $name,
                        'count' => $count,
                        'is_population' => $name === 'Population',
                    ];
                }
            }
        }

        if (count($costs) > 1) {
            usort($costs, static fn (array $a, array $b): int => strcmp((string) $a['resource'], (string) $b['resource']));
        }

        return $costs;
    }

    private function findChildByTagName(DOMElement $parent, string $tagName): ?DOMElement
    {
        $target = strtolower($tagName);
        /** @var DOMNode $child */
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->nodeName) === $target) {
                return $child;
            }
        }

        $elements = $parent->getElementsByTagName($tagName);

        return $elements->length > 0 && $elements->item(0) instanceof DOMElement ? $elements->item(0) : null;
    }

    /**
     * @return list<DOMElement>
     */
    private function findChildrenByTagName(DOMElement $parent, string $tagName): array
    {
        $target = strtolower($tagName);
        $result = [];
        /** @var DOMNode $child */
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->nodeName) === $target) {
                $result[] = $child;
            }
        }

        if ($result === []) {
            /** @var DOMElement $child */
            foreach ($parent->getElementsByTagName($tagName) as $child) {
                $result[] = $child;
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $attributes
     */
    private function getAttributeAny(DOMElement $element, array $attributes): string
    {
        foreach ($attributes as $attr) {
            if ($element->hasAttribute($attr)) {
                return $element->getAttribute($attr);
            }
        }

        return '';
    }

    /**
     * @return list<SimpleXMLElement>
     */
    private function findSimpleXmlItems(SimpleXMLElement $parent, ?string $containerTag, string $itemTag): array
    {
        $result = [];

        if ($containerTag !== null) {
            $container = $parent->{$containerTag} ?? $parent->{ucfirst($containerTag)} ?? null;
            if ($container !== null) {
                foreach ($container->{$itemTag} ?? $container->{ucfirst($itemTag)} ?? [] as $item) {
                    $result[] = $item;
                }
            }
        } else {
            foreach ($parent->{$itemTag} ?? $parent->{ucfirst($itemTag)} ?? [] as $item) {
                $result[] = $item;
            }
        }

        return $result;
    }
}
