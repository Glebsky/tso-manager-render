<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Enums\TaskStatus;

/**
 * All step outcomes of a sequence run, rebuilt from `payload.step_results`
 * so the same summary is produced whether the run stayed in one process or
 * was handed off between delayed jobs.
 */
final class StepResultCollection
{
    /** @var array<int, StepOutcome> */
    private array $outcomes = [];

    public function add(StepOutcome $outcome): void
    {
        $this->outcomes[$outcome->stepIndex] = $outcome;
        ksort($this->outcomes);
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions  `payload.actions`
     * @param  array<int|string, mixed>  $stepResults  `payload.step_results`
     */
    public static function fromPayload(array $actions, array $stepResults): self
    {
        $collection = new self;

        foreach ($stepResults as $index => $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $stepIndex = (int) $index;
            $actionType = (string) ($actions[$stepIndex]['task_type'] ?? 'unknown');
            $collection->add(StepOutcome::fromArray($stepIndex, $actionType, $raw));
        }

        return $collection;
    }

    public function hasFailure(): bool
    {
        return array_any($this->outcomes, static fn (StepOutcome $o): bool => $o->isFailed());
    }

    /**
     * A step executed successfully in this run (steps done by an earlier
     * attempt do not count, mirroring the inline executor's semantics).
     */
    public function hasSuccess(): bool
    {
        return array_any($this->outcomes, static fn (StepOutcome $o): bool => $o->isCompleted() && ! $o->alreadyDone);
    }

    public function hasSkipped(): bool
    {
        return array_any($this->outcomes, static fn (StepOutcome $o): bool => $o->isSkipped());
    }

    public function finalStatus(): TaskStatus
    {
        return $this->hasFailure() ? TaskStatus::Failed : TaskStatus::Completed;
    }

    /**
     * Raw `Step N [type]: …; Step M [type]: …` text, without the result prefix.
     */
    public function summary(): string
    {
        return implode('; ', array_map(static fn (StepOutcome $o): string => $o->summaryLine(), $this->outcomes));
    }

    /**
     * Summary with the `OK:` / `PARTIAL:` / `ERROR:` / `SKIPPED:` prefix for `last_result`.
     */
    public function formattedSummary(): string
    {
        return TaskResultSummary::formatSequence(
            $this->summary(),
            $this->hasFailure(),
            $this->hasSuccess(),
            $this->hasSkipped(),
        );
    }
}
