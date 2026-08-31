<?php

namespace App\Http\Resources;

use App\Models\MarketServerConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MarketServerConnection
 */
class PublicServerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $worldName = $this->resolveWorldName();

        return [
            'id' => $this->id,
            'server_id' => $this->server_id,
            'locale' => $this->locale,
            'world_name' => $worldName,
            'sync_status' => $this->sync_status,
        ];
    }

    private function resolveWorldName(): string
    {
        if ($this->account?->server_name) {
            return $this->account->server_name;
        }

        $name = preg_replace('/\s+Settlers\s+Market$/i', '', $this->display_name);
        $name = preg_replace('/\s+Market(\s*\([^)]*\))?$/i', '', (string) $name);
        $name = trim((string) $name);

        return $name !== '' ? $name : strtoupper($this->server_id);
    }
}
