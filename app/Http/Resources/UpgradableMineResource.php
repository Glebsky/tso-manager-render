<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{grid: int, building_name: string, deposit_name: string, level: int, max_level: int, is_active: bool, upgrade_in_progress: bool, allowed: bool, reason: string} $resource
 */
class UpgradableMineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{grid: int, building_name: string, deposit_name: string, level: int, max_level: int, is_active: bool, upgrade_in_progress: bool, allowed: bool, reason: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'grid' => (int) $this->resource['grid'],
            'building_name' => (string) $this->resource['building_name'],
            'deposit_name' => (string) $this->resource['deposit_name'],
            'level' => (int) $this->resource['level'],
            'max_level' => (int) $this->resource['max_level'],
            'is_active' => (bool) $this->resource['is_active'],
            'upgrade_in_progress' => (bool) $this->resource['upgrade_in_progress'],
            'allowed' => (bool) $this->resource['allowed'],
            'reason' => (string) $this->resource['reason'],
        ];
    }
}
