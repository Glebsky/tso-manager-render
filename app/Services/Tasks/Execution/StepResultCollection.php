<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

final class StepResultCollection
{
    /**
     * @param  array<int, StepOutcome>  $outcomes
     */
    public function __construct(private array $outcomes = []) {}

    public function add(StepOutcome $outcome): void
    {
        $this->outcomes[$outcome->stepIndex] = $outcome;
    }

    public function hasFailure(): bool
    {
        foreach ($this->outcomes as $outcome) {
            if (! $outcome->isSuccess) {
                return true;
            }
        }

        return false;
    }

    public function hasSuccess(): bool
    {
        foreach ($this->outcomes as $outcome) {
            if ($outcome->isSuccess && ! $outcome->isSkipped) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{status: string, error: ?string}>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->outcomes as $index => $outcome) {
            $result[$index] = $outcome->toArray();
        }

        return $result;
    }

    /**
     * @param  array<int|string, mixed>  $rawStepResults
     */
    public static function fromArray(array $rawStepResults): self
    {
        $collection = new self;
        foreach ($rawStepResults as $index => $raw) {
            $status = (string) ($raw['status'] ?? 'completed');
            $error = isset($raw['error']) ? (string) $raw['error'] : null;
            $stepIndex = (int) $index;

            if ($status === 'failed') {
                $collection->add(StepOutcome::failed($stepIndex, 'unknown', $error ?? 'unknown error'));
            } else {
                $collection->add(StepOutcome::skipped($stepIndex, 'unknown'));
            }
        }

        return $collection;
    }
}
