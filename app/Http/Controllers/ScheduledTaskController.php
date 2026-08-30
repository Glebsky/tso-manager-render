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
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * RESTful HTTP entry point for the task planner.
 */
class ScheduledTaskController extends Controller
{
    public function __construct(private readonly ScheduledTaskService $tasks) {}

    /**
     * Show the task planner view payload with paginated tasks.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->input('per_page', 50);
        $perPage = max(1, min(100, $perPage));

        $paginator = ScheduledTask::with('account:id,username,nickname')
            ->latest()
            ->paginate($perPage);

        return ScheduledTaskResource::collection($paginator)->additional([
            'accounts' => AccountResource::collection($this->tasks->accounts()),
            'meta' => [
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Create a new scheduled task.
     */
    public function store(StoreScheduledTaskRequest $request): JsonResponse
    {
        $task = $this->tasks->create($request->attributesForTask());
        $resource = new ScheduledTaskResource($task);

        return $resource
            ->additional([
                'success' => true,
                'message' => 'Task scheduled.',
                'task' => $resource,
                'meta' => [
                    'server_time' => now()->toIso8601String(),
                ],
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing scheduled task.
     */
    public function update(UpdateScheduledTaskRequest $request, ScheduledTask $task): ScheduledTaskResource
    {
        $updated = $this->tasks->update($task, $request->attributesForTask());
        $resource = new ScheduledTaskResource($updated);

        return $resource
            ->additional([
                'success' => true,
                'message' => 'Task updated.',
                'task' => $resource,
                'meta' => [
                    'server_time' => now()->toIso8601String(),
                ],
            ]);
    }

    /**
     * Toggle is_active on/off.
     */
    public function toggle(ScheduledTask $task): ScheduledTaskResource
    {
        $task = $this->tasks->toggle($task);
        $resource = new ScheduledTaskResource($task);

        return $resource->additional([
            'success' => true,
            'message' => 'Task '.($task->is_active ? 'activated' : 'paused').'.',
            'task' => $resource,
        ]);
    }

    /**
     * Delete a scheduled task.
     */
    public function destroy(ScheduledTask $task): Response
    {
        $this->tasks->delete($task);

        return response()->noContent();
    }

    /**
     * Execute a scheduled task immediately.
     */
    public function execute(ScheduledTask $task): JsonResponse
    {
        $executedTask = $this->tasks->execute($task);
        $isQueuedOrRunning = $this->tasks->isBusy($executedTask);
        $resource = new ScheduledTaskResource($executedTask);

        $responseResource = $resource
            ->additional([
                'success' => true,
                'queued' => $isQueuedOrRunning,
                'task' => $resource,
                'message' => $isQueuedOrRunning
                    ? 'Task queued for background execution.'
                    : ($executedTask->last_result ?? 'Task execution completed.'),
                'meta' => [
                    'server_time' => now()->toIso8601String(),
                ],
            ]);

        return $responseResource->response()->setStatusCode($isQueuedOrRunning ? 202 : 200);
    }

    /**
     * Lightweight status endpoint for polling a single task execution.
     */
    public function status(ScheduledTask $task): ScheduledTaskResource
    {
        $task = $this->tasks->withAccount($task);
        $resource = new ScheduledTaskResource($task);

        return $resource
            ->additional([
                'success' => true,
                'task' => $resource,
                'meta' => [
                    'server_time' => now()->toIso8601String(),
                ],
            ]);
    }
}
