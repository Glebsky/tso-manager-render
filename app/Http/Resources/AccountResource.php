<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Account
 */
class AccountResource extends JsonResource
{
    private bool $includeZoneData = false;

    public function withZoneData(bool $include = true): static
    {
        $this->includeZoneData = $include;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'nickname' => $this->nickname,
            'status' => $this->status,
            'region' => $this->region,
            'dso_auth_user' => $this->dso_auth_user,
            'bb_url' => $this->bb_url,
            'server_name' => $this->server_name,
            'is_market_connected' => $this->is_market_connected,
            'avatar_id' => $this->avatar_id,
            'building_count' => $this->building_count,
            'last_sync_at' => $this->last_sync_at?->toIso8601String(),
            'session_updated_at' => $this->updated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'scheduled_tasks_count' => $this->whenCounted('scheduledTasks'),
            'zone_data' => $this->when($this->includeZoneData, fn () => $this->zone_data),
        ];
    }
}
