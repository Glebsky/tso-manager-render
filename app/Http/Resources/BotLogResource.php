<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BotLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BotLog
 */
class BotLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'level' => $this->level->value,
            'message' => $this->message,
            'context' => null,
            'created_at' => $this->created_at?->toIso8601String(),
            'account' => new AccountResource($this->whenLoaded('account')),
        ];
    }
}
