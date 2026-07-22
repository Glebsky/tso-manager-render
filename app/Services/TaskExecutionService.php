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

                foreach ($actions as $index => $action) {
                    // Skip steps that were already completed in a previous attempt
                    if ($index < $completedSteps) {
                        $resultsSummary[] = __('tasks.step.skipped', ['step' => $index + 1, 'type' => $action['task_type']]);

                        continue;
                    }

                    $actionType = $action['task_type'];
                    $actionPayload = $action['payload'] ?? [];
                    $delay = (int) ($action['delay_seconds'] ?? 0);

                    // Execute step
                    $stepResult = $this->executeSingleAction($account, $actionType, $actionPayload);

                    // Update completed steps atomically
                    $completedSteps = $index + 1;
                    $task->update([
                        'completed_steps' => $completedSteps,
                    ]);

                    $resultsSummary[] = __('tasks.step.ok', ['step' => $index + 1, 'type' => $actionType, 'bytes' => strlen($stepResult)]);

                    BotLog::create([
                        'account_id' => $account->id,
                        'level' => 'success',
                        'message' => __('tasks.log.step_success', ['id' => $task->id, 'step' => $index + 1, 'type' => $actionType]),
                    ]);

                    // Delay between steps if not the last step
                    if ($index < count($actions) - 1 && $delay > 0) {
                        sleep($delay);
                    }
                }

                $result = implode('; ', $resultsSummary);
            } else {
                $result = $this->executeSingleAction($account, $task->task_type, $payload);
            }

            $nextStatus = 'completed';
            $updateData = [
                'status' => $nextStatus,
                'last_run_at' => now(),
                'last_result' => 'OK: '.(strlen($result) > 100 ? substr($result, 0, 97).'...' : $result),
                'completed_steps' => 0, // Reset step progress after successful completion
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

            $updateData = [
                'status' => 'failed',
                'last_run_at' => now(),
                'last_result' => 'ERROR: '.$errorMsg,
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
     * Execute a single action step.
     */
    public function executeSingleAction($account, string $taskType, array $payload): string
    {
        switch ($taskType) {
            case 'stop_production':
                $grid = $payload['grid'] ?? 0;

                return $this->amfService->stopProduction($account, (int) $grid);

            case 'start_production':
                $grid = $payload['grid'] ?? 0;

                return $this->amfService->startProduction($account, (int) $grid);

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

                $parsed = $this->zoneParser->parse($result);
                $errorCode = $parsed['errorCode'] ?? 0;
                if ($errorCode !== 0) {
                    $errorMsg = GameErrorResolver::getMessage((int) $errorCode);
                    throw new GameServerErrorException((int) $errorCode, $errorMsg);
                }

                return $result;

            case 'send_geologist':
            case 'send_explorer':
                $taskTypeVal = $payload['task_type'] ?? 0;
                $subTaskId = $payload['sub_task_id'] ?? 0;
                $uniqueId1 = $payload['unique_id1'] ?? 0;
                $uniqueId2 = $payload['unique_id2'] ?? 0;

                return $this->amfService->sendSpecialist($account, (int) $taskTypeVal, (int) $subTaskId, (int) $uniqueId1, (int) $uniqueId2);

            default:
                throw new UnknownTaskActionException($taskType);
        }
    }
}
