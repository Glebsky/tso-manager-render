<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = is_array($this->resource) ? $this->resource : [];

        return [
            'sync_interval' => (int) ($settings['sync_interval'] ?? 30),
            'log_retention_days' => (int) ($settings['log_retention_days'] ?? 30),
            'combat_simulator_url' => array_key_exists('combat_simulator_url', $settings)
                ? ($settings['combat_simulator_url'] !== null ? (string) $settings['combat_simulator_url'] : null)
                : null,
            'server_time' => now()->toIso8601String(),
        ];
    }
}
