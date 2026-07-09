<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ScheduledTask;
use Illuminate\Http\Request;

class ScheduledTaskController extends Controller
{
    /**
     * Show the task planner view.
     */
    public function index()
    {
        $tasks    = ScheduledTask::with('account')->latest()->get();
        $accounts = Account::orderBy('username')->get();

        return response()->json([
            'tasks'    => $tasks,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Create a new scheduled task.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id'  => 'required|exists:accounts,id',
            'task_type'   => 'required|string|in:stop_production,start_production,apply_buff,send_geologist,send_explorer',
            'payload'     => 'required|array',
            'run_at_time' => 'required|date_format:H:i',
        ]);

        $task = ScheduledTask::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Task scheduled.',
            'task'    => $task
        ], 201);
    }

    /**
     * Toggle is_active on/off.
     */
    public function toggle(ScheduledTask $task)
    {
        $task->update(['is_active' => !$task->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Task ' . ($task->is_active ? 'activated' : 'paused') . '.',
            'task'    => $task
        ]);
    }

    /**
     * Delete a scheduled task.
     */
    public function destroy(ScheduledTask $task)
    {
        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Task deleted.'
        ]);
    }
}
