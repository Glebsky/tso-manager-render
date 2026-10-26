<?php

declare(strict_types=1);

namespace App\Services\Tasks\Contracts;

use App\Models\Account;
use App\Services\Tasks\Execution\TaskActionResult;

/** Semantic outcomes for compound commands; handle() remains the legacy text adapter. */
interface ResultAwareTaskActionHandlerInterface extends TaskActionHandlerInterface
{
    /** @param array<string, mixed> $payload */
    public function handleResult(Account $account, array $payload): TaskActionResult;
}
