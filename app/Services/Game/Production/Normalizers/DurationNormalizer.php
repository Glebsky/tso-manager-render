<?php

declare(strict_types=1);

namespace App\Services\Game\Production\Normalizers;

use DOMElement;
use DOMNode;
use InvalidArgumentException;
use SimpleXMLElement;

final class DurationNormalizer
{
    private const array ATTRIBUTES = [
        'duration',
        'productionTime',
        'productionTimeSeconds',
    ];

    public function fromElement(DOMElement|SimpleXMLElement $el): int
    {
        if ($el instanceof DOMElement) {
            foreach (self::ATTRIBUTES as $attr) {
                if ($el->hasAttribute($attr)) {
                    $val = $el->getAttribute($attr);
                    if ($val !== '') {
                        return (int) $val;
                    }
                }
            }

            // Check nested <Property Type="ProductionTime" Value="..."/>
            /** @var DOMNode $child */
            foreach ($el->childNodes as $child) {
                if ($child instanceof DOMElement && strtolower($child->nodeName) === 'properties') {
                    /** @var DOMNode $prop */
                    foreach ($child->childNodes as $prop) {
                        if ($prop instanceof DOMElement && strtolower($prop->nodeName) === 'property') {
                            $type = $prop->getAttribute('Type') ?: $prop->getAttribute('type');
                            if ($type === 'ProductionTime') {
                                $val = $prop->getAttribute('Value') ?: $prop->getAttribute('value');

                                return (int) $val;
                            }
                        }
                    }
                }
            }

            throw new InvalidArgumentException("No duration attribute on <{$el->nodeName}>");
        }

        // SimpleXMLElement
        foreach (self::ATTRIBUTES as $attr) {
            if (isset($el[$attr])) {
                return (int) $el[$attr];
            }
        }

        if (isset($el->Properties->Property)) {
            foreach ($el->Properties->Property as $prop) {
                $type = (string) ($prop['Type'] ?? $prop['type'] ?? '');
                if ($type === 'ProductionTime') {
                    return (int) ($prop['Value'] ?? $prop['value'] ?? 0);
                }
            }
        }

        throw new InvalidArgumentException("No duration attribute on <{$el->getName()}>");
    }
}
