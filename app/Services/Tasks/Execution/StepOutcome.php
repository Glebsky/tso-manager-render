<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

/**
 * Result of one sequence step, as persisted in `payload.step_results[i]`
 * and rendered in the task's `last_result` summary.
 *
 * The persisted shape is `{status, error, reason?, buff?, already_done?}`;
 * the frontend reads `status`, `error` and `reason` (Tasks.vue), the other
 * keys only feed the summary.
 */
final readonly class StepOutcome
{
    public const string COMPLETED = 'completed';

    public const string SKIPPED = 'skipped';

    public const string FAILED = 'failed';

    /**
     * @param  ?string  $error  persisted error (JSON payload of TaskExecutionException or plain message)
     * @param  ?string  $reason  why a skippable step was skipped
     * @param  ?string  $buff  name of the missing buff when the skip was caused by BuffUnavailableException
     * @param  bool  $alreadyDone  step was completed by an earlier attempt, not executed in this run
     */
    private function __construct(
        public int $stepIndex,
        public string $actionType,
        public string $status,
        public ?string $error = null,
        public ?string $reason = null,
        public ?string $buff = null,
        public bool $alreadyDone = false,
        public ?string $message = null,
    ) {}

    public static function completed(int $stepIndex, string $actionType, ?string $message = null): self
    {
        return new self($stepIndex, $actionType, self::COMPLETED, message: TaskResultText::message($message));
    }

    /**
     * A step finished by a previous attempt that this run did not execute.
     */
    public static function alreadyDone(int $stepIndex, string $actionType): self
    {
        return new self($stepIndex, $actionType, self::COMPLETED, alreadyDone: true);
    }

    public static function skipped(int $stepIndex, string $actionType, string $reason, ?string $buff = null): self
    {
        return new self($stepIndex, $actionType, self::SKIPPED, reason: $reason, buff: $buff);
    }

    public static function failed(int $stepIndex, string $actionType, string $error): self
    {
        return new self($stepIndex, $actionType, self::FAILED, error: $error);
    }

    /**
     * Rebuild an outcome from a persisted `step_results` entry.
     *
     * @param  array<string, mixed>  $raw  the `step_results[i]` entry
     */
    public static function fromArray(int $stepIndex, string $actionType, array $raw): self
    {
        $status = (string) ($raw['status'] ?? self::COMPLETED);

        return match ($status) {
            self::FAILED => self::failed(
                $stepIndex,
                $actionType,
                is_string($raw['error'] ?? null) ? $raw['error'] : 'unknown error',
            ),
            self::SKIPPED => self::skipped(
                $stepIndex,
                $actionType,
                is_string($raw['reason'] ?? null) ? $raw['reason'] : '',
                is_string($raw['buff'] ?? null) ? $raw['buff'] : null,
            ),
            default => ($raw['already_done'] ?? false) === true
                ? self::alreadyDone($stepIndex, $actionType)
                : self::completed($stepIndex, $actionType, is_string($raw['message'] ?? null) ? $raw['message'] : null),
        };
    }

    public function isCompleted(): bool
    {
        return $this->status === self::COMPLETED;
    }

    public function isSkipped(): bool
    {
        return $this->status === self::SKIPPED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::FAILED;
    }

    /**
     * Entry for `payload.step_results[i]`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $entry = ['status' => $this->status, 'error' => $this->error];

        if ($this->message !== null) {
            $entry['message'] = $this->message;
        }

        if ($this->isSkipped()) {
            $entry['reason'] = (string) $this->reason;
        }

        if ($this->buff !== null) {
            $entry['buff'] = $this->buff;
        }

        if ($this->alreadyDone) {
            $entry['already_done'] = true;
        }

        return $entry;
    }

    /**
     * Human-readable error: the `message` of a serialized TaskExecutionException
     * payload, or the plain message.
     */
    public function readableError(): ?string
    {
        if ($this->error === null) {
            return null;
        }

        if (str_starts_with($this->error, '{')) {
            $decoded = json_decode($this->error, true);
            if (is_array($decoded) && ! empty($decoded['message'])) {
                return (string) $decoded['message'];
            }
        }

        return $this->error;
    }

    /**
     * One line of the task's `last_result` summary.
     */
    public function summaryLine(): string
    {
        $params = ['step' => $this->stepIndex + 1, 'type' => $this->actionType];

        if ($this->alreadyDone) {
            return (string) __('tasks.step.skipped', $params);
        }

        if ($this->isFailed()) {
            return (string) __('tasks.step.error', $params + ['error' => $this->readableError()]);
        }

        if ($this->isSkipped()) {
            if ($this->buff !== null) {
                return (string) __('tasks.step.skipped_buff', $params + ['buff' => $this->buff]);
            }

            return (string) __('tasks.step.skipped_reason', $params + ['reason' => $this->reason]);
        }

        return (string) __('tasks.step.done', $params).($this->message !== null ? ' — '.$this->message : '');
    }
}
