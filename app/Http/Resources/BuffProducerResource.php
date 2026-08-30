<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array<string, mixed> $resource
 */
class BuffProducerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'grid' => (int) ($this->resource['grid'] ?? 0),
            'building_name' => (string) ($this->resource['building_name'] ?? ''),
            'production_type' => (int) ($this->resource['production_type'] ?? 0),
            'upgrade_level' => isset($this->resource['upgrade_level']) ? (int) $this->resource['upgrade_level'] : null,
            'upgrade_in_progress' => (bool) ($this->resource['upgrade_in_progress'] ?? false),
            'production_active' => (bool) ($this->resource['production_active'] ?? true),
            'queue' => $this->resource['queue'] ?? [
                'used' => 0,
                'orders' => [],
            ],
            'recipes' => $this->resource['recipes'] ?? [],
        ];
    }
}
