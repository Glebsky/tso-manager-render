<?php

declare(strict_types=1);

namespace App\Services\Game\Production\Normalizers;

use DOMElement;
use DOMNode;
use SimpleXMLElement;

final class InstantFinishCostNormalizer
{
    private const array ATTRIBUTES = [
        'instantFinishCost',
        'instantBuildCosts',
        'InstantBuildCosts',
    ];

    public function fromElement(DOMElement|SimpleXMLElement $el): ?int
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

            // Check nested <Property Type="InstantBuildCost" Value="..."/> (P-58)
            /** @var DOMNode $child */
            foreach ($el->childNodes as $child) {
                if ($child instanceof DOMElement && strtolower($child->nodeName) === 'properties') {
                    /** @var DOMNode $prop */
                    foreach ($child->childNodes as $prop) {
                        if ($prop instanceof DOMElement && strtolower($prop->nodeName) === 'property') {
                            $type = $prop->getAttribute('Type') ?: $prop->getAttribute('type');
                            if ($type === 'InstantBuildCost') {
                                $val = $prop->getAttribute('Value') ?: $prop->getAttribute('value');

                                return (int) $val;
                            }
                        }
                    }
                }
            }

            return null;
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
                if ($type === 'InstantBuildCost') {
                    return (int) ($prop['Value'] ?? $prop['value'] ?? 0);
                }
            }
        }

        return null;
    }
}
