<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

final readonly class StepOutcome
{
    public function __construct(
        public int $stepIndex,
        public string $actionType,
        public bool $isSuccess,
        public bool $isSkipped,
        public ?string $output = null,
        public ?string $errorMessage = null,
    ) {}

    public static function success(int $stepIndex, string $actionType, string $output): self
    {
        return new self($stepIndex, $actionType, true, false, $output, null);
    }

    public static function failed(int $stepIndex, string $actionType, string $errorMessage): self
    {
        return new self($stepIndex, $actionType, false, false, null, $errorMessage);
    }

    public static function skipped(int $stepIndex, string $actionType): self
    {
        return new self($stepIndex, $actionType, true, true, null, null);
    }

    /**
     * @return array{status: string, error: ?string}
     */
    public function toArray(): array
    {
        if ($this->isSkipped) {
            return ['status' => 'completed', 'error' => null];
        }

        return [
            'status' => $this->isSuccess ? 'completed' : 'failed',
            'error' => $this->errorMessage,
        ];
    }
}
