<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ScheduledTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ScheduledTask
 */
class ScheduledTaskResource extends JsonResource
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
            'title' => $this->title,
            'action_type' => $this->action_type,
            'cron_expression' => $this->cron_expression,
            'payload' => $this->payload,
            'is_active' => (bool) $this->is_active,
            'status' => $this->status,
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'completed_steps' => $this->completed_steps,
            'last_result' => $this->last_result,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'account' => new AccountResource($this->whenLoaded('account')),
        ];
    }
}
