<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{grid: int, deposit_name: string, mine_name: string, amount: int, max_amount: int, allowed: bool, reason: string} $resource
 */
class BuildableDepositResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{grid: int, deposit_name: string, mine_name: string, amount: int, max_amount: int, allowed: bool, reason: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'grid' => (int) $this->resource['grid'],
            'deposit_name' => (string) $this->resource['deposit_name'],
            'mine_name' => (string) $this->resource['mine_name'],
            'amount' => (int) $this->resource['amount'],
            'max_amount' => (int) $this->resource['max_amount'],
            'allowed' => (bool) $this->resource['allowed'],
            'reason' => (string) $this->resource['reason'],
        ];
    }
}
