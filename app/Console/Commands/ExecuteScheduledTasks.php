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
    protected $signature   = 'tso:execute-tasks';
    protected $description = 'Execute scheduled TSO tasks that match the current time (±1 minute tolerance).';

    private TsoAuthService $authService;
    private TsoAmfService  $amfService;

    public function __construct(TsoAuthService $authService, TsoAmfService $amfService)
    {
        parent::__construct();
        $this->authService = $authService;
        $this->amfService  = $amfService;
    }

    public function handle(): int
    {
        $now         = Carbon::now();
        $currentTime = $now->format('H:i');

        // Also check 1 minute before (tolerance)
        $prevMinute = $now->copy()->subMinute()->format('H:i');

        $tasks = ScheduledTask::where('is_active', true)
            ->where(function ($query) use ($currentTime, $prevMinute) {
                $query->whereRaw("TO_CHAR(run_at_time, 'HH24:MI') = ?", [$currentTime])
                      ->orWhereRaw("TO_CHAR(run_at_time, 'HH24:MI') = ?", [$prevMinute]);
            })
            ->where(function ($query) use ($now) {
                // Don't re-run if already ran within last 2 minutes
                $query->whereNull('last_run_at')
                      ->orWhere('last_run_at', '<', $now->copy()->subMinutes(2));
            })
            ->with('account')
            ->get();

        if ($tasks->isEmpty()) {
            $this->info("No tasks to run at {$currentTime}.");
            return self::SUCCESS;
        }

        foreach ($tasks as $task) {
            $this->processTask($task);
        }

        $this->info("Processed {$tasks->count()} task(s).");
        return self::SUCCESS;
    }

    private function processTask(ScheduledTask $task): void
    {
        $account = $task->account;
        $this->info("Running task #{$task->id} [{$task->task_type}] for account [{$account->username}]");

        try {
            // Authenticate if needed
            if (!$this->authService->isAuthenticated($account)) {
                $this->authService->login($account);
                $account->refresh();
            }

            $payload = $task->payload ?? [];
            $result  = '';

            switch ($task->task_type) {
                case 'stop_production':
                    $grid   = $payload['grid'] ?? 0;
                    $result = $this->amfService->stopProduction($account, (int) $grid);
                    break;

                case 'start_production':
                    $grid   = $payload['grid'] ?? 0;
                    $result = $this->amfService->startProduction($account, (int) $grid);
                    break;

                case 'apply_buff':
                    $grid      = $payload['grid'] ?? 0;
                    $uniqueId1 = $payload['unique_id1'] ?? 0;
                    $uniqueId2 = $payload['unique_id2'] ?? 0;
                    $result    = $this->amfService->applyBuff($account, (int) $grid, (int) $uniqueId1, (int) $uniqueId2);
                    break;

                case 'send_geologist':
                case 'send_explorer':
                    $taskType  = $payload['task_type'] ?? 0;
                    $subTaskId = $payload['sub_task_id'] ?? 0;
                    $uniqueId1 = $payload['unique_id1'] ?? 0;
                    $uniqueId2 = $payload['unique_id2'] ?? 0;
                    $result    = $this->amfService->sendSpecialist($account, (int) $taskType, (int) $subTaskId, (int) $uniqueId1, (int) $uniqueId2);
                    break;

                default:
                    throw new Exception("Unknown task type: {$task->task_type}");
            }

            $task->update([
                'last_run_at' => now(),
                'last_result' => 'OK: AMF response received (' . strlen($result) . ' bytes)',
            ]);

            BotLog::create([
                'account_id' => $account->id,
                'level'      => 'success',
                'message'    => "Scheduled [{$task->task_type}] executed successfully.",
            ]);

            $this->info("  → Success.");

        } catch (Exception $e) {
            $errorMsg = $e->getMessage();

            $task->update([
                'last_run_at' => now(),
                'last_result' => 'ERROR: ' . $errorMsg,
            ]);

            BotLog::create([
                'account_id' => $account->id,
                'level'      => 'error',
                'message'    => "Scheduled [{$task->task_type}] failed: {$errorMsg}",
            ]);

            $this->error("  → Failed: {$errorMsg}");
        }
    }
}
