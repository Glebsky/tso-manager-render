<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\TaskStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<TaskStatus, TaskStatus|string>
 */
class SafeTaskStatusCast implements CastsAttributes
{
    /**
     * Cast the given value to a TaskStatus enum safely.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): TaskStatus
    {
        if ($value instanceof TaskStatus) {
            return $value;
        }

        if ($value === null || $value === '') {
            return TaskStatus::Pending;
        }

        $stringVal = (string) $value;

        $status = TaskStatus::tryFrom($stringVal);
        if ($status !== null) {
            return $status;
        }

        if ($stringVal === 'canceled') {
            return TaskStatus::Cancelled;
        }

        return TaskStatus::Pending;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof TaskStatus) {
            return $value->value;
        }

        if (is_string($value)) {
            $status = TaskStatus::tryFrom($value);

            return $status !== null ? $status->value : $value;
        }

        return TaskStatus::Pending->value;
    }
}
