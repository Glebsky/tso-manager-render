<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Json Resource representation for a popular market item.
 */
class PopularItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->resource) ? $this->resource : (array) $this->resource;

        return [
            'item_id' => (string) ($data['item_id'] ?? ''),
            'item_name' => (string) ($data['item_name'] ?? ''),
            'offers_count' => (int) ($data['offers_count'] ?? 0),
            'sellers_count' => (int) ($data['sellers_count'] ?? 0),
            'total_volume' => (float) ($data['total_volume'] ?? 0),
        ];
    }
}
