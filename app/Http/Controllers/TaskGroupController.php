<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Tasks\ReorderTaskGroupsRequest;
use App\Http\Requests\Tasks\StoreTaskGroupRequest;
use App\Http\Requests\Tasks\UpdateTaskGroupRequest;
use App\Http\Resources\TaskGroupResource;
use App\Models\TaskGroup;
use App\Services\Tasks\TaskGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class TaskGroupController extends Controller
{
    public function __construct(private readonly TaskGroupService $groupService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $accountId = $request->query('account_id');
        $groups = $accountId !== null && is_numeric($accountId)
            ? $this->groupService->forAccount((int) $accountId)
            : $this->groupService->all();

        return TaskGroupResource::collection($groups);
    }

    public function store(StoreTaskGroupRequest $request): JsonResponse
    {
        $group = $this->groupService->create($request->accountId(), $request->groupName());
        $resource = new TaskGroupResource($group);

        return $resource
            ->additional([
                'success' => true,
                'message' => 'Task group created.',
                'group' => $resource,
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTaskGroupRequest $request, TaskGroup $taskGroup): JsonResponse
    {
        $updated = $this->groupService->update($taskGroup, $request->groupName());
        $resource = new TaskGroupResource($updated);

        return $resource
            ->additional([
                'success' => true,
                'message' => 'Task group updated.',
                'group' => $resource,
            ])
            ->response();
    }

    public function destroy(TaskGroup $taskGroup): Response
    {
        $this->groupService->delete($taskGroup);

        return response()->noContent();
    }

    public function reorder(ReorderTaskGroupsRequest $request): JsonResponse
    {
        $this->groupService->reorder($request->groupIds());

        return response()->json([
            'success' => true,
            'message' => 'Task groups reordered.',
        ]);
    }
}
