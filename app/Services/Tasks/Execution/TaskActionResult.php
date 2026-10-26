<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Exceptions\ActionOutcomeException;
use App\Exceptions\ActionSkippedException;

final readonly class TaskActionResult
{
    private function __construct(public string $status, public string $message) {}

    public static function completed(string $message): self
    {
        return new self(StepOutcome::COMPLETED, $message);
    }

    public static function skipped(string $message): self
    {
        return new self(StepOutcome::SKIPPED, $message);
    }

    public static function failed(string $message): self
    {
        return new self(StepOutcome::FAILED, $message);
    }

    /** Adapt a semantic result to the existing executor's exception contract, outside post-send catches. */
    public function completedMessage(): string
    {
        return match ($this->status) {
            StepOutcome::COMPLETED => $this->message,
            StepOutcome::SKIPPED => throw new ActionSkippedException($this->message),
            default => throw new ActionOutcomeException($this->message),
        };
    }
}
