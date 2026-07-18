<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BotLog;
use App\Models\ScheduledTask;
use Illuminate\Http\Request;

class ScheduledTaskController extends Controller
{
    /**
     * Show the task planner view.
     */
    public function index()
    {
        $tasks = ScheduledTask::with('account')->latest()->get();
        $accounts = Account::orderBy('username')->get();

        return response()->json([
            'tasks' => $tasks,
            'accounts' => $accounts,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Create a new scheduled task.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'task_type' => 'required|string|in:stop_production,start_production,apply_buff,send_geologist,send_explorer,sequence',
            'payload' => 'required|array',
            'schedule_type' => 'required|string|in:daily,once,interval',
            'run_at_time' => 'required_if:schedule_type,daily|nullable|date_format:H:i',
            'run_at_datetime' => 'required_if:schedule_type,once|nullable|date',
            'interval_hours' => 'required_if:schedule_type,interval|nullable|integer|min:0',
            'interval_minutes' => 'required_if:schedule_type,interval|nullable|integer|min:0',
        ]);

        if ($request->input('task_type') === 'sequence') {
            $request->validate([
                'payload.actions' => 'required|array|min:1',
                'payload.actions.*.task_type' => 'required|string|in:stop_production,start_production,apply_buff,send_geologist,send_explorer',
                'payload.actions.*.payload' => 'required|array',
                'payload.actions.*.delay_seconds' => 'required|integer|min:0',
            ]);
        }

        if ($request->input('schedule_type') === 'interval') {
            $hours = (int) $request->input('interval_hours', 0);
            $mins = (int) $request->input('interval_minutes', 0);
            if ($hours === 0 && $mins === 0) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => [
                        'interval_hours' => ['Interval must be at least 1 minute.'],
                    ],
                ], 422);
            }
        }

        $task = ScheduledTask::create($validated);

        BotLog::create([
            'account_id' => $task->account_id,
            'level' => 'info',
            'message' => "Task #{$task->id} [{$task->task_type}] scheduled with type [{$task->schedule_type}] for account.",
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task scheduled.',
            'task' => $task,
            'server_time' => now()->toIso8601String(),
        ], 201);
    }

    /**
     * Toggle is_active on/off.
     */
    public function toggle(ScheduledTask $task)
    {
        $task->update(['is_active' => ! $task->is_active]);

        BotLog::create([
            'account_id' => $task->account_id,
            'level' => 'info',
            'message' => "Task #{$task->id} [{$task->task_type}] toggled to ".($task->is_active ? 'active' : 'inactive').'.',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task '.($task->is_active ? 'activated' : 'paused').'.',
            'task' => $task,
        ]);
    }

    /**
     * Delete a scheduled task.
     */
    public function destroy(ScheduledTask $task)
    {
        // Keep ID for log reference before delete
        $taskId = $task->id;
        $taskType = $task->task_type;
        $accountId = $task->account_id;

        $task->delete();

        BotLog::create([
            'account_id' => $accountId,
            'level' => 'info',
            'message' => "Task #{$taskId} [{$taskType}] deleted.",
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task deleted.',
        ]);
    }

    /**
     * Execute a scheduled task immediately.
     */
    public function execute(ScheduledTask $task)
    {
        \Illuminate\Support\Facades\Artisan::call('tso:execute-tasks', ['--task' => $task->id]);

        $task->refresh();

        $success = ! str_starts_with($task->last_result ?? '', 'ERROR:');

        return response()->json([
            'success' => $success,
            'task' => $task,
            'message' => $task->last_result,
        ]);
    }
}
