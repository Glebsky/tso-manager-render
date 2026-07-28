<?php

namespace App\Http\Controllers;

use App\Jobs\ExecuteScheduledTaskJob;
use App\Models\Account;
use App\Models\BotLog;
use App\Models\ScheduledTask;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ScheduledTaskController extends Controller
{
    /**
     * Show the task planner view.
     */
    public function index()
    {
        $tasks = ScheduledTask::with('account')->orderBy('id', 'desc')->get();
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
            'name' => 'nullable|string|max:255',
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

        $account = Account::findOrFail($request->input('account_id'));

        if ($request->input('task_type') === 'apply_buff') {
            $this->validateBuffPayload($account, $request->input('payload'));
        } elseif ($request->input('task_type') === 'sequence') {
            $actions = $request->input('payload.actions', []);
            foreach ($actions as $index => $action) {
                if (($action['task_type'] ?? '') === 'apply_buff') {
                    $this->validateBuffPayload($account, $action['payload'] ?? [], "payload.actions.{$index}.payload.");
                }
            }
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
            'message' => "[Task][Task#{$task->id}] ".__('logs.task.scheduled', ['id' => $task->id, 'type' => $task->task_type, 'schedule' => $task->schedule_type]),
        ]);

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
    public function update(Request $request, ScheduledTask $task)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
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

        $account = Account::findOrFail($request->input('account_id'));

        if ($request->input('task_type') === 'apply_buff') {
            $this->validateBuffPayload($account, $request->input('payload'));
        } elseif ($request->input('task_type') === 'sequence') {
            $actions = $request->input('payload.actions', []);
            foreach ($actions as $index => $action) {
                if (($action['task_type'] ?? '') === 'apply_buff') {
                    $this->validateBuffPayload($account, $action['payload'] ?? [], "payload.actions.{$index}.payload.");
                }
            }
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

        $task->update($validated);

        BotLog::create([
            'account_id' => $task->account_id,
            'level' => 'info',
            'message' => "[Task][Task#{$task->id}] ".__('logs.task.updated', ['id' => $task->id, 'type' => $task->task_type]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Task updated.',
            'task' => $task->fresh(),
            'server_time' => now()->toIso8601String(),
        ]);
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
            'message' => "[Task][Task#{$task->id}] ".($task->is_active
                ? __('logs.task.enabled', ['id' => $task->id, 'type' => $task->task_type])
                : __('logs.task.disabled', ['id' => $task->id, 'type' => $task->task_type])),
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
            'message' => "[Task][Task#{$taskId}] ".__('logs.task.deleted', ['id' => $taskId, 'type' => $taskType]),
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
        $token = (string) Str::uuid();

        $payload = $task->payload ?? [];
        unset($payload['step_results']);

        $task->update([
            'status' => 'queued',
            'queued_at' => now(),
            'execution_token' => $token,
            'completed_steps' => 0,
            'last_result' => null,
            'payload' => $payload,
        ]);

        ExecuteScheduledTaskJob::dispatch($task->id, $token);

        $task->refresh();
        $task->load('account');

        $isQueuedOrRunning = in_array($task->status, ['queued', 'running'], true);

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
    public function status(ScheduledTask $task)
    {
        $task->load('account');

        $isQueuedOrRunning = in_array($task->status, ['queued', 'running'], true);

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

    /**
     * Validate payload for apply_buff action/step.
     */
    private function validateBuffPayload(Account $account, array $payload, string $prefix = 'payload.'): void
    {
        $rules = [
            $prefix.'target_scope' => 'nullable|in:self,friend',
            $prefix.'grid' => 'required|integer|min:1',
            $prefix.'unique_id1' => 'required|integer',
            $prefix.'unique_id2' => 'required|integer',
            $prefix.'amount' => 'nullable|integer|min:1',
            $prefix.'target_player_id' => 'required_if:'.$prefix.'target_scope,friend|nullable|integer|min:1',
            $prefix.'target_player_name' => 'nullable|string|max:255',
        ];

        request()->validate($rules);

        $targetScope = $payload['target_scope'] ?? 'self';
        $amount = $payload['amount'] ?? 1;

        $zoneData = $account->zone_data ? json_decode($account->zone_data, true) : [];
        $buffs = $zoneData['availableBuffs'] ?? $zoneData['buffs'] ?? [];
        $buffFound = false;
        foreach ($buffs as $buff) {
            $u1 = $buff['uniqueId1'] ?? $buff['uniqueID1'] ?? $buff['uniqueID']['uniqueID1'] ?? $buff['uniqueID']['uniqueId1'] ?? $buff['uniqueId']['uniqueId1'] ?? null;
            $u2 = $buff['uniqueId2'] ?? $buff['uniqueID2'] ?? $buff['uniqueID']['uniqueID2'] ?? $buff['uniqueID']['uniqueId2'] ?? $buff['uniqueId']['uniqueId2'] ?? null;
            if ($u1 == $payload['unique_id1'] && $u2 == $payload['unique_id2']) {
                $buffFound = true;
                $availableAmount = $buff['amount'] ?? 0;
                if ($availableAmount < $amount) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        $prefix.'amount' => [__('tasks.error.insufficient_buffs', ['available' => $availableAmount, 'required' => $amount])],
                    ]);
                }
                break;
            }
        }

        if (! $buffFound) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $prefix.'unique_id1' => [__('tasks.error.buff_not_found')],
            ]);
        }

        if ($targetScope === 'friend') {
            $friendId = (int) $payload['target_player_id'];

            if ($friendId < 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $prefix.'target_player_id' => [__('tasks.error.invalid_friend_id')],
                ]);
            }

            $friends = $zoneData['friends'] ?? [];
            $friendFound = false;
            foreach ($friends as $friend) {
                if (isset($friend['id']) && (int) $friend['id'] === $friendId) {
                    $friendFound = true;
                    break;
                }
            }

            if (! $friendFound) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $prefix.'target_player_id' => [__('tasks.error.friend_not_in_list')],
                ]);
            }

            $cacheKey = "friend-zone:{$account->id}:{$friendId}";
            $cachedZone = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if ($cachedZone) {
                $friendZoneData = json_decode($cachedZone, true);
                $buildings = $friendZoneData['buildings'] ?? [];
                $gridFound = false;
                foreach ($buildings as $building) {
                    if (($building['buildingGrid'] ?? null) == $payload['grid']) {
                        $gridFound = true;
                        break;
                    }
                }
                if (! $gridFound) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        $prefix.'grid' => [__('tasks.error.friend_building_not_found_grid', ['grid' => $payload['grid']])],
                    ]);
                }
            } else {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $prefix.'target_player_id' => [__('tasks.error.friend_zone_not_cached')],
                ]);
            }
        }
    }
}
