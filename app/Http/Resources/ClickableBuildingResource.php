<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\Game\ClickableBuildingDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ClickableBuildingDto $resource
 */
class ClickableBuildingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{grid: int, building_name: string, kind: string, available: bool|null}
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
