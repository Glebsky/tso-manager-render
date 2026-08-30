<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Zone\ZoneAmfExecutor;
use App\Services\Zone\ZoneResourceCategorizer;
use Exception;

/**
 * High-level service for parsing raw AMF zone responses.
 */
class ZoneParserService
{
    public function __construct(
        private readonly ZoneAmfExecutor $executor,
        private readonly ZoneResourceCategorizer $categorizer,
    ) {}

    /**
     * Parse raw AMF zone response into structured JSON with resource categorization.
     *
     * @param  string  $rawAmf  Raw AMF binary response
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function parse(string $rawAmf): array
    {
        $result = $this->executor->execute($rawAmf);

        if (isset($result['resources']) && is_array($result['resources'])) {
            foreach ($result['resources'] as &$resource) {
                $name = (string) ($resource['name'] ?? $resource['name_string'] ?? '');
                $resource['category'] = $this->categorizer->getCategory($name);
            }
        }

        return $result;
    }
}
