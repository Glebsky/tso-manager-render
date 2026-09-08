<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Enums\TaskResultPrefix;

final class TaskResultSummary
{
    public static function formatSingle(string $result, bool $isSuccess = true): string
    {
        $prefix = $isSuccess ? TaskResultPrefix::Ok : TaskResultPrefix::Error;
        $maxLen = (int) config('game.tasks.max_result_length', 1000);
        $truncated = strlen($result) > $maxLen ? substr($result, 0, $maxLen - 3).'...' : $result;

        return $prefix->format($truncated);
    }

    public static function formatSequence(string $summaryText, bool $hasError, bool $hasSuccess): string
    {
        $prefix = match (true) {
            $hasError && $hasSuccess => TaskResultPrefix::Partial,
            $hasError => TaskResultPrefix::Error,
            default => TaskResultPrefix::Ok,
        };

        $maxLen = (int) config('game.tasks.max_result_length', 1000);
        $truncated = strlen($summaryText) > $maxLen ? substr($summaryText, 0, $maxLen - 3).'...' : $summaryText;

        return $prefix->format($truncated);
    }
}
