<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GameServerErrorException;
use App\Exceptions\TaskAccountNotFoundException;
use App\Exceptions\TaskExecutionException;
use App\Exceptions\TaskInactiveException;
use App\Exceptions\TokenMismatchException;
use App\Models\BotLog;
use App\Models\ScheduledTask;
use App\Services\Tasks\TaskHandlerRegistry;
use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

class TaskExecutionService
{
    public function __construct(
        private readonly TsoAuthService $authService,
        private readonly TsoAmfService $amfService,
        private readonly ZoneParserService $zoneParser,
        private readonly TaskHandlerRegistry $handlerRegistry,
    ) {}

    /**
     * Execute the given scheduled task.
     *
     * @throws Exception
     */
    public function execute(ScheduledTask $task, ?string $expectedToken = null, bool $force = false): string
    {
        $task->refresh();

        if (! $task->is_active && ! $force) {
            $this->logInactive($task, 'execute', $expectedToken);

            throw new TaskInactiveException($task->id);
        }

        if (! $task->is_active) {
            Log::info("[Task] Task #{$task->id} is paused; executing anyway because force=true (manual run)");
        }

        if ($expectedToken !== null && $task->execution_token !== null && $task->execution_token !== $expectedToken) {
            throw new TokenMismatchException($task->id, $expectedToken, $task->execution_token);
        }

        $account = $task->account;
        if (! $account) {
            throw new TaskAccountNotFoundException($task->id);
        }

        $task->update([
            'status' => 'running',
        ]);

        try {
            if (! $this->authService->isAuthenticated($account)) {
                $this->authService->login($account);
                $account->refresh();
            }

            $payload = $task->payload ?? [];

            if ($task->task_type === 'sequence') {
                return $this->executeSequenceTask($task, $account, $payload);
            }

            return $this->executeSingleTask($task, $account, $payload);
        } catch (Throwable $e) {
            $this->handleOverallFailure($task, $account->id, $e);
            throw $e;
        }
    }

    /**
     * Execute a sequence task.
     *
     * @param  array<string, mixed>  $payload
     */
    private function executeSequenceTask(ScheduledTask $task, mixed $account, array $payload): string
    {
        $actions = $payload['actions'] ?? [];
        $resultsSummary = [];
        $completedSteps = (int) ($task->completed_steps ?? 0);
        $stepResults = $payload['step_results'] ?? [];
        $hasStepError = false;

        foreach ($actions as $index => $action) {
            $actionType = (string) $action['task_type'];
            $actionPayload = (array) ($action['payload'] ?? []);
            $delay = (int) ($action['delay_seconds'] ?? 0);

            if ($index < $completedSteps) {
                $resultsSummary[] = __('tasks.step.skipped', ['step' => $index + 1, 'type' => $actionType]);
                if (! isset($stepResults[$index])) {
                    $stepResults[$index] = [
                        'status' => 'completed',
                        'error' => null,
                    ];
                }

                continue;
            }

            try {
                $stepResult = $this->executeSingleActionWithRetry($account, $actionType, $actionPayload);

                $stepResults[$index] = [
                    'status' => 'completed',
                    'error' => null,
                ];

                $resultsSummary[] = __('tasks.step.ok', ['step' => $index + 1, 'type' => $actionType, 'bytes' => strlen($stepResult)]);

                BotLog::create([
                    'account_id' => $account->id,
                    'level' => 'success',
                    'message' => "[Task][Task#{$task->id}] ".__('logs.task.step_completed', ['id' => $task->id, 'step' => $index + 1, 'type' => $actionType]),
                ]);
            } catch (Throwable $e) {
                $hasStepError = true;
                $errorMsg = $e instanceof TaskExecutionException
                    ? (string) json_encode($e->toPayload())
                    : $e->getMessage();

                $stepResults[$index] = [
                    'status' => 'failed',
                    'error' => $errorMsg,
                ];

                $resultsSummary[] = __('tasks.step.error', ['step' => $index + 1, 'type' => $actionType, 'error' => $errorMsg]);

                BotLog::create([
                    'account_id' => $account->id,
                    'level' => 'error',
                    'message' => "[Task][Task#{$task->id}] ".__('logs.task.step_failed', ['id' => $task->id, 'step' => $index + 1, 'type' => $actionType, 'error' => $errorMsg]),
                ]);
            }

            $completedSteps = $index + 1;
            $task->update([
                'completed_steps' => $completedSteps,
            ]);

            if ($index < count($actions) - 1 && $delay > 0) {
                sleep($delay);
            }
        }

        $result = implode('; ', $resultsSummary);
        $payload['step_results'] = $stepResults;

        $nextStatus = $hasStepError ? 'failed' : 'completed';
        $lastResultPrefix = $hasStepError ? 'ERROR: ' : 'OK: ';

        $updateData = [
            'status' => $nextStatus,
            'last_run_at' => now(),
            'last_result' => $lastResultPrefix.(strlen($result) > 150 ? substr($result, 0, 147).'...' : $result),
            'payload' => $payload,
            'completed_steps' => 0,
            'execution_token' => null,
        ];

        if ($task->schedule_type === 'once') {
            $updateData['is_active'] = false;
        }

        $task->update($updateData);

        $logLevel = $hasStepError ? 'error' : 'success';
        BotLog::create([
            'account_id' => $account->id,
            'level' => $logLevel,
            'message' => "[Task][Task#{$task->id}] ".($hasStepError
                ? __('logs.task.completed_with_errors', ['type' => $task->task_type, 'result' => strlen($result) > 100 ? substr($result, 0, 97).'...' : $result])
                : __('logs.task.completed', ['type' => $task->task_type, 'result' => strlen($result) > 100 ? substr($result, 0, 97).'...' : $result])),
        ]);

        return $result;
    }

    /**
     * Execute a single (non-sequence) task.
     *
     * @param  array<string, mixed>  $payload
     */
    private function executeSingleTask(ScheduledTask $task, mixed $account, array $payload): string
    {
        try {
            $result = $this->executeSingleActionWithRetry($account, $task->task_type, $payload);
            $payload['step_results'] = [
                [
                    'status' => 'completed',
                    'error' => null,
                ],
            ];

            $updateData = [
                'status' => 'completed',
                'last_run_at' => now(),
                'last_result' => 'OK: '.(strlen($result) > 100 ? substr($result, 0, 97).'...' : $result),
                'payload' => $payload,
                'completed_steps' => 0,
                'execution_token' => null,
            ];

            if ($task->schedule_type === 'once') {
                $updateData['is_active'] = false;
            }

            $task->update($updateData);

            BotLog::create([
                'account_id' => $account->id,
                'level' => 'success',
                'message' => "[Task][Task#{$task->id}] ".__('logs.task.completed', ['type' => $task->task_type, 'result' => strlen($result) > 100 ? substr($result, 0, 97).'...' : $result]),
            ]);

            return $result;
        } catch (Throwable $e) {
            $errorMsg = $e instanceof TaskExecutionException
                ? (string) json_encode($e->toPayload())
                : $e->getMessage();

            $payload['step_results'] = [
                [
                    'status' => 'failed',
                    'error' => $errorMsg,
                ],
            ];

            $updateData = [
                'status' => 'failed',
                'last_run_at' => now(),
                'last_result' => 'ERROR: '.$errorMsg,
                'payload' => $payload,
                'completed_steps' => 0,
                'execution_token' => null,
            ];

            if ($task->schedule_type === 'once') {
                $updateData['is_active'] = false;
            }

            $task->update($updateData);

            BotLog::create([
                'account_id' => $account->id,
                'level' => 'error',
                'message' => "[Task][Task#{$task->id}] ".__('logs.task.failed', ['type' => $task->task_type, 'error' => $errorMsg]),
            ]);

            throw $e;
        }
    }

    private function handleOverallFailure(ScheduledTask $task, mixed $accountId, Throwable $e): void
    {
        $errorMsg = $e instanceof TaskExecutionException
            ? (string) json_encode($e->toPayload())
            : $e->getMessage();

        $payload = $task->payload ?? [];
        if ($task->task_type === 'sequence' && isset($payload['actions']) && is_array($payload['actions'])) {
            $completedSteps = (int) ($task->completed_steps ?? 0);
            $stepResults = $payload['step_results'] ?? [];
            if (! isset($stepResults[$completedSteps])) {
                $stepResults[$completedSteps] = [
                    'status' => 'failed',
                    'error' => $errorMsg,
                ];
            }
            $payload['step_results'] = $stepResults;
        } else {
            $payload['step_results'] = [
                [
                    'status' => 'failed',
                    'error' => $errorMsg,
                ],
            ];
        }

        $updateData = [
            'status' => 'failed',
            'last_run_at' => now(),
            'last_result' => 'ERROR: '.$errorMsg,
            'payload' => $payload,
            'execution_token' => null,
        ];

        if ($task->schedule_type === 'once') {
            $updateData['is_active'] = false;
        }

        $task->update($updateData);

        BotLog::create([
            'account_id' => $accountId,
            'level' => 'error',
            'message' => "[Task][Task#{$task->id}] ".__('logs.task.failed', ['type' => $task->task_type, 'error' => $errorMsg]),
        ]);
    }

    /**
     * Execute exactly one pending step of a sequence task.
     *
     * @return array{finished: bool, nextDelay: int}
     *
     * @throws Exception
     */
    public function executeSequenceStep(ScheduledTask $task, ?string $expectedToken = null, bool $force = false): array
    {
        $task->refresh();

        if (! $task->is_active && ! $force) {
            $this->logInactive($task, 'executeSequenceStep', $expectedToken);

            throw new TaskInactiveException($task->id);
        }

        if ($expectedToken !== null && $task->execution_token !== null && $task->execution_token !== $expectedToken) {
            throw new TokenMismatchException($task->id, $expectedToken, $task->execution_token);
        }

        if ($task->task_type !== 'sequence') {
            throw new Exception("Task #{$task->id} is not a sequence task.");
        }

        $account = $task->account;
        if (! $account) {
            throw new TaskAccountNotFoundException($task->id);
        }

        $payload = $task->payload ?? [];
        $actions = $payload['actions'] ?? [];
        $stepResults = $payload['step_results'] ?? [];
        $index = (int) ($task->completed_steps ?? 0);

        if ($index === 0 && ! empty($stepResults)) {
            $stepResults = [];
            unset($payload['step_results']);
        }

        if (empty($actions) || $index >= count($actions)) {
            return $this->finalizeSequence($task, (int) $account->id, $payload, $stepResults);
        }

        $task->update([
            'status' => 'running',
            'queued_at' => now(),
            'payload' => $payload,
        ]);

        if (! $this->authService->isAuthenticated($account)) {
            $this->authService->login($account);
            $account->refresh();
        }

        $action = $actions[$index];
        $actionType = (string) $action['task_type'];
        $actionPayload = (array) ($action['payload'] ?? []);
        $delay = (int) ($action['delay_seconds'] ?? 0);

        try {
            $stepResult = $this->executeSingleActionWithRetry($account, $actionType, $actionPayload);

            $stepResults[$index] = [
                'status' => 'completed',
                'error' => null,
            ];

            BotLog::create([
                'account_id' => $account->id,
                'level' => 'success',
                'message' => "[Task][Task#{$task->id}] ".__('logs.task.step_completed', ['id' => $task->id, 'step' => $index + 1, 'type' => $actionType]),
            ]);
        } catch (Throwable $e) {
            $errorMsg = $e instanceof TaskExecutionException
                ? (string) json_encode($e->toPayload())
                : $e->getMessage();

            $stepResults[$index] = [
                'status' => 'failed',
                'error' => $errorMsg,
            ];

            BotLog::create([
                'account_id' => $account->id,
                'level' => 'error',
                'message' => "[Task][Task#{$task->id}] ".__('logs.task.step_failed', ['id' => $task->id, 'step' => $index + 1, 'type' => $actionType, 'error' => $errorMsg]),
            ]);
        }

        $payload['step_results'] = $stepResults;
        $task->update([
            'completed_steps' => $index + 1,
            'payload' => $payload,
        ]);

        if ($index + 1 >= count($actions)) {
            return $this->finalizeSequence($task, (int) $account->id, $payload, $stepResults);
        }

        return [
            'finished' => false,
            'nextDelay' => $delay,
        ];
    }

    /**
     * @return array{finished: bool, nextDelay: int}
     */
    private function finalizeSequence(ScheduledTask $task, int $accountId, array $payload, array $stepResults): array
    {
        $hasStepError = false;
        $hasStepSuccess = false;
        $summaryParts = [];

        foreach ($stepResults as $i => $stepResult) {
            if (($stepResult['status'] ?? null) === 'failed') {
                $hasStepError = true;
                $summaryParts[] = __('tasks.step.error_short', ['step' => $i + 1, 'error' => $stepResult['error'] ?? 'unknown']);
            } else {
                $hasStepSuccess = true;
                $summaryParts[] = __('tasks.step.ok_short', ['step' => $i + 1]);
            }
        }

        $result = implode('; ', $summaryParts);
        $payload['step_results'] = $stepResults;

        $lastResultPrefix = ($hasStepError && $hasStepSuccess)
            ? 'PARTIAL: '
            : ($hasStepError ? 'ERROR: ' : 'OK: ');

        $updateData = [
            'status' => $hasStepError ? 'failed' : 'completed',
            'last_run_at' => now(),
            'last_result' => $lastResultPrefix.(strlen($result) > 150 ? substr($result, 0, 147).'...' : $result),
            'payload' => $payload,
            'completed_steps' => 0,
            'execution_token' => null,
        ];

        if ($task->schedule_type === 'once') {
            $updateData['is_active'] = false;
            Log::info("[Task] Task #{$task->id} deactivated by finalizeSequence(): schedule_type='once'");
        }

        $task->update($updateData);

        BotLog::create([
            'account_id' => $accountId,
            'level' => $hasStepError ? 'error' : 'success',
            'message' => "[Task][Task#{$task->id}] ".($hasStepError
                ? __('logs.task.completed_with_errors', ['type' => $task->task_type, 'result' => strlen($result) > 100 ? substr($result, 0, 97).'...' : $result])
                : __('logs.task.completed', ['type' => $task->task_type, 'result' => strlen($result) > 100 ? substr($result, 0, 97).'...' : $result])),
        ]);

        return [
            'finished' => true,
            'nextDelay' => 0,
        ];
    }

    /**
     * Diagnostics for the "inactive or paused" case: without this snapshot the
     * log only says the task was inactive, never in which state it got there.
     */
    private function logInactive(ScheduledTask $task, string $entryPoint, ?string $expectedToken): void
    {
        Log::warning(sprintf(
            '[Task] Task #%d rejected in %s() because is_active=false. state: type=%s status=%s schedule=%s token=%s expected_token=%s completed_steps=%s queued_at=%s last_run_at=%s updated_at=%s last_result=%s',
            $task->id,
            $entryPoint,
            (string) $task->task_type,
            (string) $task->status,
            (string) $task->schedule_type,
            $task->execution_token ?? 'null',
            $expectedToken ?? 'null',
            (string) $task->completed_steps,
            $task->queued_at?->toDateTimeString() ?? 'null',
            $task->last_run_at?->toDateTimeString() ?? 'null',
            $task->updated_at?->toDateTimeString() ?? 'null',
            (string) ($task->last_result ?? 'null')
        ));
    }

    /**
     * Execute a single action step with session error (1012, 1005) retry logic.
     *
     * @param  array<string, mixed>  $payload
     */
    public function executeSingleActionWithRetry(mixed $account, string $taskType, array $payload, int $maxAttempts = 2): string
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->executeSingleAction($account, $taskType, $payload);
            } catch (GameServerErrorException $e) {
                if (in_array($e->getCode(), [1005, 1012], true) && $attempt < $maxAttempts) {
                    Log::info("[TaskExecution] Action [{$taskType}] hit game error {$e->getCode()}; resetting session and retrying (attempt {$attempt}/{$maxAttempts})");
                    @unlink($this->authService->getCookieFile($account));
                    $this->authService->login($account);
                    $this->amfService->resetClient();
                    $account->refresh();
                    sleep(2);

                    continue;
                }
                throw $e;
            }
        }

        throw new Exception("Action [{$taskType}] failed.");
    }

    /**
     * Execute a single action step via TaskHandlerRegistry.
     *
     * @param  array<string, mixed>  $payload
     */
    public function executeSingleAction(mixed $account, string $taskType, array $payload): string
    {
        $handler = $this->handlerRegistry->getHandler($taskType);
        $result = $handler->handle($account, $payload);

        try {
            $parsed = $this->zoneParser->parse($result);
            $errorCode = (int) ($parsed['errorCode'] ?? 0);
            if ($errorCode !== 0) {
                $errorMsg = GameErrorResolver::getMessage($errorCode);
                throw new GameServerErrorException($errorCode, $errorMsg);
            }
        } catch (GameServerErrorException $e) {
            throw $e;
        } catch (Throwable $e) {
            // Ignore parse failures on non-AMF mock strings in tests
        }

        return $result;
    }
}
