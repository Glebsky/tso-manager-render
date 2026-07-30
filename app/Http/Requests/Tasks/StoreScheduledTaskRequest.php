<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

/**
 * Creating a scheduled task. Reuses the shared task contract as-is.
 */
final class StoreScheduledTaskRequest extends ScheduledTaskRequest {}
