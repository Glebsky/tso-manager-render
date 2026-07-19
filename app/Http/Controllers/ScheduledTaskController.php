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
            $u1 = $buff['uniqueId1'] ?? $buff['uniqueID1'] ?? null;
            $u2 = $buff['uniqueId2'] ?? $buff['uniqueID2'] ?? null;
            if ($u1 == $payload['unique_id1'] && $u2 == $payload['unique_id2']) {
                $buffFound = true;
                $availableAmount = $buff['amount'] ?? 0;
                if ($availableAmount < $amount) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        $prefix.'amount' => ["Недостаточно баффов в звездном меню (доступно: {$availableAmount}, требуется: {$amount})."],
                    ]);
                }
                break;
            }
        }

        if (! $buffFound) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $prefix.'unique_id1' => ['Указанный бафф не найден в инвентаре звездного меню.'],
            ]);
        }

        if ($targetScope === 'friend') {
            $friendId = (int) $payload['target_player_id'];

            if ($friendId < 1) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $prefix.'target_player_id' => ['Неверный ID друга.'],
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
                    $prefix.'target_player_id' => ['Игрок отсутствует в вашем списке друзей.'],
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
                        $prefix.'grid' => ["Здание с сеткой #{$payload['grid']} не найдено в зоне друга."],
                    ]);
                }
            } else {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $prefix.'target_player_id' => ['Зона друга не загружена или истек срок кеша. Пожалуйста, обновите ее в интерфейсе.'],
                ]);
            }
        }
    }
}
