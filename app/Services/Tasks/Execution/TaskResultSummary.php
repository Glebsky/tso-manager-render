<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Enums\TaskResultPrefix;

final class TaskResultSummary
{
    public static function formatSingle(string $result, bool $isSuccess = true): string
    {
        $prefix = $isSuccess ? TaskResultPrefix::Ok : TaskResultPrefix::Error;
        $maxLen = (int) config('game.tasks.max_result_length', 150);
        $truncated = strlen($result) > 100 ? substr($result, 0, 97).'...' : $result;

        return $prefix->format($truncated);
    }

    public static function formatSequence(string $summaryText, bool $hasError, bool $hasSuccess): string
    {
        $prefix = ($hasError && $hasSuccess)
            ? TaskResultPrefix::Partial
            : ($hasError ? TaskResultPrefix::Error : TaskResultPrefix::Ok);

        $maxLen = (int) config('game.tasks.max_result_length', 150);
        $truncated = strlen($summaryText) > $maxLen ? substr($summaryText, 0, $maxLen - 3).'...' : $summaryText;

        return $prefix->format($truncated);
    }
}
