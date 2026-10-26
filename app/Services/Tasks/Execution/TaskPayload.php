<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Enums\TaskType;
use App\Exceptions\TaskExecutionException;

final class TaskPayload
{
    /** @return array<string, mixed> */
    public static function validate(mixed $payload, bool $sequence = false): array
    {
        if (! is_array($payload) || ($payload !== [] && array_is_list($payload))) {
            throw new TaskExecutionException('error.payload_invalid', [], 422);
        }

        if ($sequence) {
            $actions = $payload['actions'] ?? null;
            if (! is_array($actions) || $actions === []) {
                throw new TaskExecutionException('error.payload_invalid', [], 422);
            }

            foreach ($actions as $action) {
                if (! is_array($action)
                    || ! is_string($action['task_type'] ?? null)
                    || TaskType::tryFrom($action['task_type']) === null
                    || $action['task_type'] === TaskType::Sequence->value
                    || (array_key_exists('payload', $action) && ! is_array($action['payload']))) {
                    throw new TaskExecutionException('error.payload_invalid', [], 422);
                }
            }
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    public static function encode(array $payload): string
    {
        // Query-builder updates bypass Eloquent casts. Bind exactly one JSON
        // string on both PostgreSQL and SQLite; never pass an array or false.
        return json_encode((object) self::validate($payload), JSON_THROW_ON_ERROR);
    }
}
