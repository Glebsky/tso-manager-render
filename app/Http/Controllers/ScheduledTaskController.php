<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Tasks\StoreScheduledTaskRequest;
use App\Http\Requests\Tasks\UpdateScheduledTaskRequest;
use App\Http\Resources\AccountResource;
use App\Http\Resources\ScheduledTaskResource;
use App\Models\ScheduledTask;
use App\Services\Tasks\ScheduledTaskService;
use Illuminate\Http\JsonResponse;

/**
 * RESTful HTTP entry point for the task planner.
 */
class ScheduledTaskController extends Controller
{
    public function __construct(private readonly ScheduledTaskService $tasks) {}

    /**
     * Show the task planner view payload.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'tasks' => ScheduledTaskResource::collection($this->tasks->tasks()),
            'accounts' => AccountResource::collection($this->tasks->accounts()),
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
            'task' => new ScheduledTaskResource($task),
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
            'task' => new ScheduledTaskResource($updated),
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
            'task' => new ScheduledTaskResource($task),
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
            'task' => new ScheduledTaskResource($task),
            'message' => $isQueuedOrRunning
                ? 'Task queued for background execution.'
                : ($task->last_result ?? 'Task execution completed.'),
        ]);
    }

    /**
     * Lightweight status endpoint for polling a single task execution.
     */
    public function status(ScheduledTask $task): JsonResponse
    {
        $task = $this->tasks->withAccount($task);

        return response()->json([
            'success' => true,
            'task' => new ScheduledTaskResource($task),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
