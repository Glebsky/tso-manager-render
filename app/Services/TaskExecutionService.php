<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BotLog;
use App\Models\ScheduledTask;
use Exception;

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
            throw new Exception("Task #{$task->id} is inactive or paused.");
        }

        if ($expectedToken !== null && $task->execution_token !== null && $task->execution_token !== $expectedToken) {
            throw new Exception("Execution token mismatch for task #{$task->id}. Expected: {$expectedToken}, found: {$task->execution_token}");
        }

        $account = $task->account;
        if (! $account) {
            throw new Exception("Account for task #{$task->id} not found.");
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
                        $resultsSummary[] = 'Step '.($index + 1)." [{$action['task_type']}]: SKIPPED (already executed)";

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

                    $resultsSummary[] = 'Step '.($index + 1)." [{$actionType}]: OK (".strlen($stepResult).' bytes)';

                    BotLog::create([
                        'account_id' => $account->id,
                        'level' => 'success',
                        'message' => "Sequence task #{$task->id} step ".($index + 1)." [{$actionType}] executed successfully.",
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
                'message' => "Scheduled [{$task->task_type}] executed successfully. ".(strlen($result) > 100 ? substr($result, 0, 97).'...' : $result),
            ]);

            return $result;

        } catch (Exception $e) {
            $errorMsg = $e->getMessage();

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
                        throw new Exception('Шаг пропущен: игрок больше не находится в списке друзей');
                    }

                    $friendZoneAmf = $this->amfService->getZone($account, $targetPlayerId);
                    $friendZoneData = $this->zoneParser->parse($friendZoneAmf);
                    $err = $friendZoneData['errorCode'] ?? 0;
                    if ($err !== 0) {
                        throw new Exception("Не удалось загрузить зону друга (код ошибки сервера: {$err})");
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
                        throw new Exception("Шаг не выполнен: здание Grid #{$grid} не найдено в зоне {$friendName}");
                    }

                    $result = $this->amfService->applyBuff($account, (int) $grid, (int) $uniqueId1, (int) $uniqueId2, (int) $amount, (int) $targetPlayerId);
                } else {
                    $result = $this->amfService->applyBuff($account, (int) $grid, (int) $uniqueId1, (int) $uniqueId2, (int) $amount);
                }

                $parsed = $this->zoneParser->parse($result);
                $errorCode = $parsed['errorCode'] ?? 0;
                if ($errorCode !== 0) {
                    $errorMsg = $this->getBuffErrorMessage($errorCode);
                    throw new Exception("Код ошибки сервера {$errorCode}: {$errorMsg}");
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
                throw new Exception("Unknown action type: {$taskType}");
        }
    }

    /**
     * Map buff error code to message.
     */
    private function getBuffErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            22 => 'Баф нельзя применить к этому типу здания на чужой зоне',
            25 => 'На здании достигнут лимит бафов',
            27 => 'Баф можно применять только на домашней зоне владельца',
            28 => 'Применение временно заблокировано',
            46 => 'Баф нельзя применить в зоне этого типа',
            52 => 'Не выполнены условия применения',
            default => "Неизвестная ошибка сервера (код {$errorCode})",
        };
    }
}
