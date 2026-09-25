<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

/**
 * How a SequenceRunner::run() invocation ended.
 */
final readonly class SequenceRunResult
{
    private function __construct(
        public SequenceRunStatus $status,
        public string $summary,
        public int $nextDelay,
    ) {}

    /** All steps done; final state written. */
    public static function finished(string $summary): self
    {
        return new self(SequenceRunStatus::Finished, $summary, 0);
    }

    /** This process no longer owns the run (stopped, recovered, finished elsewhere). Nothing more to do. */
    public static function aborted(string $summary): self
    {
        return new self(SequenceRunStatus::Aborted, $summary, 0);
    }

    /** The next step must wait longer than this invocation's budget allows: caller re-dispatches after `$nextDelay`. */
    public static function handOff(string $summary, int $nextDelay): self
    {
        return new self(SequenceRunStatus::HandOff, $summary, $nextDelay);
    }

    public function isFinished(): bool
    {
        return $this->status === SequenceRunStatus::Finished;
    }

    public function isHandOff(): bool
    {
        return $this->status === SequenceRunStatus::HandOff;
    }
}
