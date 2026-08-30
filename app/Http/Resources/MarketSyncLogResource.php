<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MarketSyncLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializer of a market synchronization log entry.
 *
 * @mixin MarketSyncLog
 */
class MarketSyncLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->created_at->toIso8601String(),
            'server_id' => $this->server_id,
            'action' => $this->action,
            'status' => $this->status,
            'message' => $this->message,
        ];
    }
}
