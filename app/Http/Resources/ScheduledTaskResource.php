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
            'name' => $this->name,
            'account_id' => $this->account_id,
            'task_type' => $this->task_type->value,
            'schedule_type' => $this->schedule_type->value,
            'run_at_time' => $this->run_at_time,
            'run_at_datetime' => $this->run_at_datetime?->toIso8601String(),
            'interval_hours' => $this->interval_hours,
            'interval_minutes' => $this->interval_minutes,
            'payload' => $this->payload,
            'is_active' => (bool) $this->is_active,
            'status' => $this->status->value,

            'queued_at' => $this->queued_at?->toIso8601String(),
            'completed_steps' => $this->completed_steps,
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'last_result' => $this->last_result,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'account' => new AccountResource($this->whenLoaded('account')),
        ];
    }
}
