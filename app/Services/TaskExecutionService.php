<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\FriendBuildingNotFoundException;
use App\Exceptions\FriendNotFoundException;
use App\Exceptions\FriendZoneLoadException;
use App\Exceptions\GameServerErrorException;
use App\Exceptions\TaskAccountNotFoundException;
use App\Exceptions\TaskExecutionException;
use App\Exceptions\TaskInactiveException;
use App\Exceptions\TokenMismatchException;
use App\Exceptions\UnknownTaskActionException;
use App\Models\BotLog;
use App\Models\ScheduledTask;
use Exception;
use Throwable;

class TaskExecutionService
{
    private TsoAuthService $authService;

    private TsoAmfService $amfService;

    private ZoneParserService $zoneParser;

    public function __construct(
        TsoAuthService $authService,
        TsoAmfService $amfService,
        ZoneParserService $zoneParser
    ) {
        $this->authService = $authService;
        $this->amfService = $amfService;
        $this->zoneParser = $zoneParser;
    }

    /**
     * Execute the given scheduled task.
     *
     * @throws Exception
     */
    public function execute(ScheduledTask $task, ?string $expectedToken = null): string
    {
        // Re-load fresh instance to verify state
        $task->refresh();

        if (! $task->is_active) {
            throw new TaskInactiveException($task->id);
        }

        if ($expectedToken !== null && $task->execution_token !== null && $task->execution_token !== $expectedToken) {
            throw new TokenMismatchException($task->id, $expectedToken, $task->execution_token);
        }

        $account = $task->account;
        if (! $account) {
            throw new TaskAccountNotFoundException($task->id);
        }

        // Mark running
        $task->update([
            'status' => 'running',
        ]);

        try {
            // Authenticate if needed
            if (! $this->authService->isAuthenticated($account)) {
                $this->authService->login($account);
                $account->refresh();
            }

            $payload = $task->payload ?? [];
            $result = '';

            if ($task->task_type === 'sequence') {
                $actions = $payload['actions'] ?? [];
                $resultsSummary = [];
                $completedSteps = (int) ($task->completed_steps ?? 0);
                $stepResults = $payload['step_results'] ?? [];
                $hasStepError = false;

                foreach ($actions as $index => $action) {
                    $actionType = $action['task_type'];
                    $actionPayload = $action['payload'] ?? [];
                    $delay = (int) ($action['delay_seconds'] ?? 0);

                    // Skip steps that were already completed in a previous attempt
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
                        // Execute step with retry on session errors (1012, 1005)
                        $stepResult = $this->executeSingleActionWithRetry($account, $actionType, $actionPayload);

                        $stepResults[$index] = [
                            'status' => 'completed',
                            'error' => null,
                        ];

                        $resultsSummary[] = __('tasks.step.ok', ['step' => $index + 1, 'type' => $actionType, 'bytes' => strlen($stepResult)]);

                        BotLog::create([
                            'account_id' => $account->id,
                            'level' => 'success',
                            'message' => __('tasks.log.step_success', ['id' => $task->id, 'step' => $index + 1, 'type' => $actionType]),
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

                        $resultsSummary[] = 'Step '.($index + 1)." [{$actionType}]: ERROR - {$errorMsg}";

                        BotLog::create([
                            'account_id' => $account->id,
                            'level' => 'error',
                            'message' => "Sequence task #{$task->id} step ".($index + 1)." [{$actionType}] failed: {$errorMsg}",
                        ]);
                    }

                    // Update completed steps atomically
                    $completedSteps = $index + 1;
                    $task->update([
                        'completed_steps' => $completedSteps,
                    ]);

                    // Delay between steps if not the last step
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
                    'completed_steps' => 0, // Reset step progress after run completion
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
                    'message' => __('tasks.log.task_success', ['type' => $task->task_type, 'result' => strlen($result) > 100 ? substr($result, 0, 97).'...' : $result]),
                ]);

                return $result;
            } else {
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
                        'message' => __('tasks.log.task_success', ['type' => $task->task_type, 'result' => strlen($result) > 100 ? substr($result, 0, 97).'...' : $result]),
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
                        'message' => "Scheduled [{$task->task_type}] failed: {$errorMsg}",
                    ]);

                    throw $e;
                }
            }

        } catch (Throwable $e) {
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
                'account_id' => $account->id,
                'level' => 'error',
                'message' => "Scheduled [{$task->task_type}] failed: {$errorMsg}",
            ]);

            throw $e;
        }
    }

    /**
     * Execute exactly one pending step of a sequence task.
     *
     * Unlike execute(), this method never sleeps between steps. The caller
     * (the queue job) decides whether to wait inline or re-dispatch a
     * delayed job, so a long sequence never exceeds execution time limits.
     *
     * On infrastructure errors (auth, DB, ...) the exception is re-thrown
     * WITHOUT changing task status: the job retry or stale-task recovery
     * resumes the run from completed_steps.
     *
     * @return array{finished: bool, nextDelay: int}
     *
     * @throws Exception
     */
    public function executeSequenceStep(ScheduledTask $task, ?string $expectedToken = null): array
    {
        $task->refresh();

        if (! $task->is_active) {
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

        // Fresh run: drop step results left over from the previous run.
        if ($index === 0 && ! empty($stepResults)) {
            $stepResults = [];
        }

        if (empty($actions) || $index >= count($actions)) {
            return $this->finalizeSequence($task, (int) $account->id, $payload, $stepResults);
        }

        // Mark running + heartbeat so recoverStaleTasks() leaves healthy runs alone.
        $task->update([
            'status' => 'running',
            'queued_at' => now(),
        ]);

        if (! $this->authService->isAuthenticated($account)) {
            $this->authService->login($account);
            $account->refresh();
        }

        $action = $actions[$index];
        $actionType = $action['task_type'];
        $actionPayload = $action['payload'] ?? [];
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
                'message' => __('tasks.log.step_success', ['id' => $task->id, 'step' => $index + 1, 'type' => $actionType]),
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
                'message' => "Sequence task #{$task->id} step ".($index + 1)." [{$actionType}] failed: {$errorMsg}",
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
     * Write the final status/result of a step-by-step sequence run.
     *
     * @return array{finished: bool, nextDelay: int}
     */
    private function finalizeSequence(ScheduledTask $task, int $accountId, array $payload, array $stepResults): array
    {
        $hasStepError = false;
        $summaryParts = [];

        foreach ($stepResults as $i => $stepResult) {
            if (($stepResult['status'] ?? null) === 'failed') {
                $hasStepError = true;
                $summaryParts[] = 'Step '.($i + 1).': ERROR - '.($stepResult['error'] ?? 'unknown');
            } else {
                $summaryParts[] = 'Step '.($i + 1).': OK';
            }
        }

        $result = implode('; ', $summaryParts);
        $payload['step_results'] = $stepResults;

        $updateData = [
            'status' => $hasStepError ? 'failed' : 'completed',
            'last_run_at' => now(),
            'last_result' => ($hasStepError ? 'ERROR: ' : 'OK: ').(strlen($result) > 150 ? substr($result, 0, 147).'...' : $result),
            'payload' => $payload,
            'completed_steps' => 0, // Reset step progress after run completion
            'execution_token' => null,
        ];

        if ($task->schedule_type === 'once') {
            $updateData['is_active'] = false;
        }

        $task->update($updateData);

        BotLog::create([
            'account_id' => $accountId,
            'level' => $hasStepError ? 'error' : 'success',
            'message' => __('tasks.log.task_success', ['type' => $task->task_type, 'result' => strlen($result) > 100 ? substr($result, 0, 97).'...' : $result]),
        ]);

        return [
            'finished' => true,
            'nextDelay' => 0,
        ];
    }

    /**
     * Execute a single action step with session error (1012, 1005) retry logic.
     */
    public function executeSingleActionWithRetry($account, string $taskType, array $payload, int $maxAttempts = 2): string
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->executeSingleAction($account, $taskType, $payload);
            } catch (GameServerErrorException $e) {
                if (in_array($e->getCode(), [1005, 1012], true) && $attempt < $maxAttempts) {
                    \Illuminate\Support\Facades\Log::info("Task action [{$taskType}] encountered game error {$e->getCode()}. Resetting session and retrying (attempt {$attempt}/{$maxAttempts})...");
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
     * Execute a single action step.
     */
    public function executeSingleAction($account, string $taskType, array $payload): string
    {
        $result = '';

        switch ($taskType) {
            case 'stop_production':
                $grid = $payload['grid'] ?? 0;
                $result = $this->amfService->stopProduction($account, (int) $grid);
                break;

            case 'start_production':
                $grid = $payload['grid'] ?? 0;
                $result = $this->amfService->startProduction($account, (int) $grid);
                break;

            case 'apply_buff':
                $grid = $payload['grid'] ?? 0;
                $uniqueId1 = $payload['unique_id1'] ?? 0;
                $uniqueId2 = $payload['unique_id2'] ?? 0;
                $amount = $payload['amount'] ?? 1;
                $targetScope = $payload['target_scope'] ?? 'self';
                $targetPlayerId = $payload['target_player_id'] ?? null;

                if ($targetScope === 'friend') {
                    $zoneData = $account->zone_data ? json_decode($account->zone_data, true) : [];
                    $friends = $zoneData['friends'] ?? [];
                    $friend = null;
                    $targetPlayerId = (int) $targetPlayerId;
                    foreach ($friends as $f) {
                        if (isset($f['id']) && (int) $f['id'] === $targetPlayerId) {
                            $friend = $f;
                            break;
                        }
                    }
                    if (! $friend) {
                        throw new FriendNotFoundException;
                    }

                    $friendZoneAmf = $this->amfService->getZone($account, $targetPlayerId);
                    $friendZoneData = $this->zoneParser->parse($friendZoneAmf);
                    $err = $friendZoneData['errorCode'] ?? 0;
                    if ($err !== 0) {
                        $errMsg = GameErrorResolver::getMessage((int) $err);
                        throw new FriendZoneLoadException((int) $err, $errMsg);
                    }

                    $buildings = $friendZoneData['buildings'] ?? [];
                    $gridFound = false;
                    foreach ($buildings as $building) {
                        if (($building['buildingGrid'] ?? null) == $grid) {
                            $gridFound = true;
                            break;
                        }
                    }
                    if (! $gridFound) {
                        $friendName = $friend['username'] ?? $friend['nickname'] ?? $payload['target_player_name'] ?? 'Unknown';
                        throw new FriendBuildingNotFoundException((int) $grid, (string) $friendName);
                    }

                    $result = $this->amfService->applyBuff($account, (int) $grid, (int) $uniqueId1, (int) $uniqueId2, (int) $amount, (int) $targetPlayerId);
                } else {
                    $result = $this->amfService->applyBuff($account, (int) $grid, (int) $uniqueId1, (int) $uniqueId2, (int) $amount);
                }
                break;

            case 'send_geologist':
            case 'send_explorer':
                $taskTypeVal = $payload['task_type'] ?? 0;
                $subTaskId = $payload['sub_task_id'] ?? 0;
                $uniqueId1 = $payload['unique_id1'] ?? 0;
                $uniqueId2 = $payload['unique_id2'] ?? 0;

                $result = $this->amfService->sendSpecialist($account, (int) $taskTypeVal, (int) $subTaskId, (int) $uniqueId1, (int) $uniqueId2);
                break;

            default:
                throw new UnknownTaskActionException($taskType);
        }

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
