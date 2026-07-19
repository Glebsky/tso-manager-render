<?php

namespace App\Console\Commands;

use App\Models\BotLog;
use App\Models\ScheduledTask;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;

class ExecuteScheduledTasks extends Command
{
    protected $signature = 'tso:execute-tasks {--task= : Run a specific task ID directly}';

    protected $description = 'Execute scheduled TSO tasks that match the current time (±1 minute tolerance).';

    private TsoAuthService $authService;

    private TsoAmfService $amfService;

    private \App\Services\ZoneParserService $zoneParser;

    public function __construct(TsoAuthService $authService, TsoAmfService $amfService, \App\Services\ZoneParserService $zoneParser)
    {
        parent::__construct();
        $this->authService = $authService;
        $this->amfService = $amfService;
        $this->zoneParser = $zoneParser;
    }

    public function handle(): int
    {
        $now = Carbon::now();

        $singleTaskId = $this->option('task');
        if ($singleTaskId) {
            $task = ScheduledTask::with('account')->find($singleTaskId);
            if (! $task) {
                $this->error("Task #{$singleTaskId} not found.");

                return self::FAILURE;
            }
            $this->processTask($task);

            return self::SUCCESS;
        }

        $activeTasks = ScheduledTask::where('is_active', true)->with('account')->get();
        $tasksToRun = [];

        foreach ($activeTasks as $task) {
            $shouldRun = false;

            if ($task->schedule_type === 'daily' || is_null($task->schedule_type)) {
                if ($task->run_at_time) {
                    $currentTime = $now->format('H:i');
                    $prevMinute = $now->copy()->subMinute()->format('H:i');
                    $runTime = Carbon::parse($task->run_at_time)->format('H:i');

                    if ($runTime === $currentTime || $runTime === $prevMinute) {
                        if (is_null($task->last_run_at) || $task->last_run_at->lt($now->copy()->subMinutes(2))) {
                            $shouldRun = true;
                        }
                    }
                }
            } elseif ($task->schedule_type === 'once') {
                if ($task->run_at_datetime && is_null($task->last_run_at)) {
                    if ($now->greaterThanOrEqualTo($task->run_at_datetime)) {
                        $shouldRun = true;
                    }
                }
            } elseif ($task->schedule_type === 'interval') {
                $hours = (int) $task->interval_hours;
                $minutes = (int) $task->interval_minutes;
                $intervalTotalMinutes = ($hours * 60) + $minutes;

                if ($intervalTotalMinutes > 0) {
                    $baseline = $task->last_run_at ?? $task->created_at;
                    if ($baseline) {
                        $diffInMinutes = $now->diffInMinutes($baseline);
                        if ($diffInMinutes >= $intervalTotalMinutes) {
                            $shouldRun = true;
                        }
                    }
                }
            }

            if ($shouldRun) {
                $tasksToRun[] = $task;
            }
        }

        if (empty($tasksToRun)) {
            $this->info("No tasks to run at {$now->toDateTimeString()}.");

            return self::SUCCESS;
        }

        foreach ($tasksToRun as $task) {
            $this->processTask($task);
        }

        $this->info('Processed '.count($tasksToRun).' task(s).');

        return self::SUCCESS;
    }

    private function processTask(ScheduledTask $task): void
    {
        $account = $task->account;
        $this->info("Running task #{$task->id} [{$task->task_type}] for account [{$account->username}]");

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
                $executedCount = 0;
                $resultsSummary = [];

                foreach ($actions as $index => $action) {
                    $actionType = $action['task_type'];
                    $actionPayload = $action['payload'] ?? [];
                    $delay = (int) ($action['delay_seconds'] ?? 0);

                    $this->info('  → Executing step '.($index + 1).'/'.count($actions).": [{$actionType}]");

                    // Run the single action
                    $stepResult = $this->executeSingleAction($account, $actionType, $actionPayload);
                    $executedCount++;
                    $resultsSummary[] = 'Step '.($index + 1)." [{$actionType}]: OK (".strlen($stepResult).' bytes)';

                    // Create step bot log
                    BotLog::create([
                        'account_id' => $account->id,
                        'level' => 'success',
                        'message' => "Sequence task #{$task->id} step ".($index + 1)." [{$actionType}] executed successfully.",
                    ]);

                    // If not the last action, and delay > 0, sleep
                    if ($index < count($actions) - 1 && $delay > 0) {
                        $this->info("  → Sleeping for {$delay} seconds before next step...");
                        sleep($delay);
                    }
                }

                $result = implode('; ', $resultsSummary);
            } else {
                $result = $this->executeSingleAction($account, $task->task_type, $payload);
            }

            $updateData = [
                'last_run_at' => now(),
                'last_result' => 'OK: '.(strlen($result) > 100 ? substr($result, 0, 97).'...' : $result),
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

            $this->info('  → Success.');

        } catch (Exception $e) {
            $errorMsg = $e->getMessage();

            $updateData = [
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

            $this->error("  → Failed: {$errorMsg}");
        }
    }

    private function executeSingleAction($account, string $taskType, array $payload): string
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

                    $this->info("  → Fetching friend zone fresh for target player [{$targetPlayerId}]");
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
