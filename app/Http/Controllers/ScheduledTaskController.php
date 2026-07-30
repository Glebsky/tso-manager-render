<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Tasks\StoreScheduledTaskRequest;
use App\Http\Requests\Tasks\UpdateScheduledTaskRequest;
use App\Models\ScheduledTask;
use App\Services\Tasks\ScheduledTaskService;
use Illuminate\Http\JsonResponse;

/**
 * HTTP entry point for the task planner.
 *
 * Validation lives in the Tasks form requests, behaviour lives in
 * ScheduledTaskService. This class only translates between the two.
 */
class ScheduledTaskController extends Controller
{
    public function __construct(private readonly ScheduledTaskService $tasks) {}

    /**
     * Show the task planner view.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'tasks' => $this->tasks->tasks(),
            'accounts' => $this->tasks->accounts(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Create a new scheduled task.
     */
    public function store(StoreScheduledTaskRequest $request): JsonResponse
    {
        $task = $this->tasks->create($request->attributesForTask());

        return response()->json([
            'success' => true,
            'message' => 'Task scheduled.',
            'task' => $task,
            'server_time' => now()->toIso8601String(),
        ], 201);
    }

    /**
     * Update an existing scheduled task.
     */
    public function update(UpdateScheduledTaskRequest $request, ScheduledTask $task): JsonResponse
    {
        $updated = $this->tasks->update($task, $request->attributesForTask());

        return response()->json([
            'success' => true,
            'message' => 'Task updated.',
            'task' => $updated,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Toggle is_active on/off.
     */
    public function toggle(ScheduledTask $task): JsonResponse
    {
        $task = $this->tasks->toggle($task);

        return response()->json([
            'success' => true,
            'message' => 'Task '.($task->is_active ? 'activated' : 'paused').'.',
            'task' => $task,
        ]);
    }

    /**
     * Delete a scheduled task.
     */
    public function destroy(ScheduledTask $task): JsonResponse
    {
        $this->tasks->delete($task);

        return response()->json([
            'success' => true,
            'message' => 'Task deleted.',
        ]);
    }

    /**
     * Execute a scheduled task immediately.
     */
    public function execute(ScheduledTask $task): JsonResponse
    {
        $task = $this->tasks->execute($task);
        $isQueuedOrRunning = $this->tasks->isBusy($task);

        return response()->json([
            'success' => true,
            'queued' => $isQueuedOrRunning,
            'task' => $task,
            'message' => $isQueuedOrRunning
                ? 'Task queued for background execution.'
                : ($task->last_result ?? 'Task execution completed.'),
        ]);
    }

    /**
     * Lightweight status endpoint for polling a single task execution.
     *
     * Returns only the fields the frontend needs to track a manual run,
     * instead of the full planner payload (all tasks + all accounts).
     */
    public function status(ScheduledTask $task): JsonResponse
    {
        $task = $this->tasks->withAccount($task);
        $isQueuedOrRunning = $this->tasks->isBusy($task);

        return response()->json([
            'success' => true,
            'task' => [
                'id' => $task->id,
                'status' => $task->status,
                'is_active' => $task->is_active,
                'account_id' => $task->account_id,
                'account' => $task->account,
                'completed_steps' => $task->completed_steps,
                'last_result' => $isQueuedOrRunning ? null : $task->last_result,
                'last_run_at' => $task->last_run_at?->toIso8601String(),
                // payload is included because the UI reads payload.step_results
                // to show per-step progress and error details.
                'payload' => $task->payload,
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
