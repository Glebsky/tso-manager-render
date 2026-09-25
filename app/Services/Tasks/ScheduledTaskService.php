<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Exceptions\TaskExecutionException;
use App\Jobs\ExecuteScheduledTaskJob;
use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\TaskExecutionService;
use App\Services\Tasks\Execution\TaskStateMachine;
use App\Services\Tasks\Scheduling\ScheduleCalculator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The whole lifecycle of a scheduled task: planner data, create, update,
 * toggle, delete and manual execution.
 *
 * Validation lives in the form requests, logging lives in TaskActivityLogger,
 * HTTP shaping lives in the controller. This class owns state transitions and
 * nothing else.
 */
final class ScheduledTaskService
{
    public function __construct(
        private readonly TaskActivityLogger $logger,
        private readonly TaskExecutionService $executionService,
        private readonly TaskStateMachine $stateMachine,
        private readonly ScheduleCalculator $calculator,
    ) {}

    /**
     * @return Collection<int, ScheduledTask>
     */
    public function tasks(): Collection
    {
        return ScheduledTask::with(['account' => static function ($query): void {
            $query->select('id', 'username', 'nickname', 'region', 'status')->withExists('marketServerConnections');
        }])->orderBy('sort_order')->orderBy('id')->get();
    }

    public function find(int $id): ?ScheduledTask
    {
        return ScheduledTask::with(['account' => static function ($query): void {
            $query->select('id', 'username', 'nickname', 'region', 'status')->withExists('marketServerConnections');
        }])->find($id);
    }

    /**
     * @param  Collection<int, Account>|null  $preloadedAccounts
     * @return LengthAwarePaginator<int, ScheduledTask>
     */
    public function paginate(int $perPage = 50, ?Collection $preloadedAccounts = null): LengthAwarePaginator
    {
        if ($preloadedAccounts !== null) {
            $paginator = ScheduledTask::query()->orderBy('sort_order')->orderBy('id')->paginate($perPage);
            $accountsById = $preloadedAccounts->keyBy('id');
            $paginator->getCollection()->each(static function (ScheduledTask $task) use ($accountsById): void {
                $task->setRelation('account', $accountsById->get($task->account_id));
            });

            return $paginator;
        }

        return ScheduledTask::query()
            ->with([
                'account' => static function ($query): void {
                    $query->select('id', 'username', 'nickname', 'region', 'status')->withExists('marketServerConnections');
                },
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($perPage);
    }

    /**
     * @return Collection<int, Account>
     */
    public function accounts(): Collection
    {
        return Account::query()
            ->select(['id', 'username', 'nickname', 'region', 'status'])
            ->withExists('marketServerConnections')
            ->orderBy('username')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ScheduledTask
    {
        $accountId = (int) ($data['account_id'] ?? 0);
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

        $data['payload'] = $this->enrichPayloadBuildingNames($accountId, $payload);

        if (! isset($data['sort_order'])) {
            $data['sort_order'] = ((int) ScheduledTask::max('sort_order')) + 1;
        }

        // FR-006: the slot is derived from the schedule fields at creation time.
        $data['next_run_at'] = $this->scheduleRunAt(new ScheduledTask($data));

        try {
            $task = ScheduledTask::create($data);
        } catch (UniqueConstraintViolationException $e) {
            if ($this->isPostgresPrimaryKeyCollision($e)) {
                Log::warning('[ScheduledTaskService] Detected postgres sequence desynchronization for scheduled_tasks, resyncing and retrying...');
                $this->resyncPostgresSequence();
                $task = ScheduledTask::create($data);
            } else {
                throw $e;
            }
        }

        $this->logger->scheduled($task);

        return $task->fresh() ?? $task;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ScheduledTask $task, array $data): ScheduledTask
    {
        $accountId = (int) ($data['account_id'] ?? $task->account_id);
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : $task->payload ?? [];

        $data['payload'] = $this->enrichPayloadBuildingNames($accountId, $payload);

        $task->fill($data);

        // FR-006: a changed schedule (or a row that never had a slot) gets a fresh one.
        if ($task->isDirty(self::SCHEDULE_FIELDS) || $task->next_run_at === null) {
            $task->next_run_at = $this->scheduleRunAt($task);
        }

        $task->save();
        $this->logger->updated($task);

        return $task->fresh() ?? $task;
    }

    /**
     * Fields that define when a task runs; changing any of them recomputes `next_run_at`.
     *
     * @var list<string>
     */
    private const array SCHEDULE_FIELDS = ['schedule_type', 'run_at_time', 'run_at_datetime', 'interval_hours', 'interval_minutes'];

    /**
     * Slot to persist after an operator (re)defined the schedule: the task's
     * current slot, or the next future one if that slot is already missed
     * (never a "missed slot" warning caused by a user action, ADR-002).
     */
    private function scheduleRunAt(ScheduledTask $task): ?Carbon
    {
        $grace = max(0, (int) (config('game.scheduler.missed_grace_minutes') ?? 5));
        $slot = $this->calculator->scheduleRunAt($task, CarbonImmutable::now(), $grace);

        return $slot !== null ? Carbon::instance($slot) : null;
    }

    /**
     * Auto-enrich payload with human-readable building name(s) from account's cached zone_data.
     * Preserves existing building_name / target_building_name if already set by user.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function enrichPayloadBuildingNames(int $accountId, array $payload): array
    {
        if ($accountId <= 0) {
            return $payload;
        }

        $account = Account::find($accountId);
        if (! $account || empty($account->zone_data)) {
            return $payload;
        }

        $zoneData = $account->zone_data;
        $buildings = $zoneData['buildings'] ?? [];
        if (! is_array($buildings) || $buildings === []) {
            return $payload;
        }

        $gridNameMap = [];
        $gridRawNameMap = [];
        foreach ($buildings as $b) {
            $grid = isset($b['buildingGrid']) ? (int) $b['buildingGrid'] : null;
            if ($grid !== null) {
                if (isset($b['name'])) {
                    $gridNameMap[$grid] = (string) $b['name'];
                }
                $raw = $b['buildingName_string'] ?? $b['buildingName'] ?? null;
                if ($raw !== null) {
                    $gridRawNameMap[$grid] = (string) $raw;
                }
            }
        }

        if ($gridNameMap === [] && $gridRawNameMap === []) {
            return $payload;
        }

        if (isset($payload['grid'])) {
            $grid = (int) $payload['grid'];
            if (! isset($payload['building_name']) && isset($gridNameMap[$grid])) {
                $payload['building_name'] = $gridNameMap[$grid];
                if (! isset($payload['name'])) {
                    $payload['name'] = $gridNameMap[$grid];
                }
            }
            if (! isset($payload['building_raw_name']) && isset($gridRawNameMap[$grid])) {
                $payload['building_raw_name'] = $gridRawNameMap[$grid];
            }
        }

        if (isset($payload['actions']) && is_array($payload['actions'])) {
            foreach ($payload['actions'] as $i => $action) {
                if (isset($action['payload']['grid']) && is_array($action['payload'])) {
                    $grid = (int) $action['payload']['grid'];
                    if (! isset($action['payload']['building_name']) && isset($gridNameMap[$grid])) {
                        $payload['actions'][$i]['payload']['building_name'] = $gridNameMap[$grid];
                        if (! isset($payload['actions'][$i]['payload']['name'])) {
                            $payload['actions'][$i]['payload']['name'] = $gridNameMap[$grid];
                        }
                    }
                    if (! isset($action['payload']['building_raw_name']) && isset($gridRawNameMap[$grid])) {
                        $payload['actions'][$i]['payload']['building_raw_name'] = $gridRawNameMap[$grid];
                    }
                }
            }
        }

        return $payload;
    }

    public function toggle(ScheduledTask $task): ScheduledTask
    {
        Log::info(sprintf(
            '[Task] Task #%d is_active %s -> %s via API toggle (status=%s, token=%s)',
            $task->id,
            $task->is_active ? 'true' : 'false',
            $task->is_active ? 'false' : 'true',
            $task->status->value,
            $task->execution_token ?? 'null'
        ));

        $data = ['is_active' => ! $task->is_active];

        // Turning a task on re-anchors its slot (FR-006): a slot that expired
        // while it was paused moves to the next future one instead of being
        // reported as missed (ADR-002).
        if (! $task->is_active) {
            $task->is_active = true;
            $data['next_run_at'] = $this->scheduleRunAt($task);
        }

        $task->update($data);

        $this->logger->toggled($task);

        return $task;
    }

    public function delete(ScheduledTask $task): void
    {
        $taskId = $task->id;
        $taskType = $task->task_type;
        $accountId = $task->account_id;

        $task->delete();

        $this->logger->deleted($accountId, $taskId, $taskType);
    }

    /**
     * Queue a manual run and return the task as the UI should see it.
     *
     * Ручной запуск выполняется даже для выключенной (is_active = false) задачи:
     * кнопка «Run now» — явное действие оператора и важнее флага расписания.
     * Сам флаг не меняется: после ручного запуска задача остаётся на паузе.
     */
    public function execute(ScheduledTask $task): ScheduledTask
    {
        $token = (string) Str::uuid();

        $payload = $task->payload ?? [];
        unset($payload['step_results']);

        $reserved = $this->stateMachine->reserve($task, $token, $payload, force: true);
        if (! $reserved) {
            throw new TaskExecutionException('error.task_busy', ['id' => $task->id], 409);
        }

        $task->refresh();

        Log::info(sprintf(
            '[Task] Manual "Run now" for task #%d [%s] (is_active=%s, schedule=%s, token=%s) — dispatched with force=true',
            $task->id,
            $task->task_type->value,
            $task->is_active ? 'true' : 'false',
            $task->schedule_type->value,
            $token
        ));

        ExecuteScheduledTaskJob::dispatch($task->id, $token, true);

        if (config('queue.default') === 'sync') {
            $task->refresh();
        }
        $task->load(['account' => static function ($query): void {
            $query->select('id', 'username', 'nickname', 'region', 'status')->withExists('marketServerConnections');
        }]);

        return $task;
    }

    /**
     * Stop/cancel a running or queued task immediately.
     *
     * Stop interrupts only the current run (ADR-001): a recurring task keeps
     * its schedule and is reserved again in its next slot, counted from the
     * moment it was stopped (ADR-003: the interval starts at the end of a run).
     * A once task has no next slot, so it is deactivated instead.
     */
    public function stop(ScheduledTask $task): ScheduledTask
    {
        $totalSteps = count($task->payload['actions'] ?? []);
        $completedSteps = $task->completed_steps ?? 0;

        $stopMessage = __('tasks.stopped_by_user', [
            'step' => $completedSteps,
            'total' => $totalSteps > 0 ? $totalSteps : 1,
        ]);

        $this->stateMachine->cancel($task, $stopMessage);

        Log::info(sprintf(
            '[Task] Task #%d cancelled by user (completed_steps: %d, total: %d)',
            $task->id,
            $completedSteps,
            $totalSteps
        ));

        return $this->withAccount($task->fresh() ?? $task);
    }

    /**
     * Run a single action of a sequence task manually.
     *
     * @return array{status: string, error: ?string, reason?: string}
     */
    public function executeAction(ScheduledTask $task, int $actionIndex): array
    {
        return $this->executionService->executeSingleSequenceAction($task, $actionIndex);
    }

    public function duplicate(ScheduledTask $task): ScheduledTask
    {
        return DB::transaction(function () use ($task): ScheduledTask {
            ScheduledTask::where('sort_order', '>', $task->sort_order)
                ->increment('sort_order');

            $duplicateName = ! empty($task->name) ? "{$task->name} (копия)" : 'Серия (копия)';

            $replica = $task->replicate([
                'last_run_at',
                'last_result',
                'queued_at',
                'heartbeat_at',
                'execution_token',
                'completed_steps',
                'next_run_at', // inactive copy; toggle() assigns a slot when it is switched on
            ]);

            $replica->name = $duplicateName;
            $replica->is_active = false;
            $replica->status = TaskStatus::Pending;
            $replica->sort_order = $task->sort_order + 1;

            try {
                $replica->save();
            } catch (UniqueConstraintViolationException $e) {
                if ($this->isPostgresPrimaryKeyCollision($e)) {
                    Log::warning('[ScheduledTaskService] Detected postgres sequence desynchronization on duplicate, resyncing and retrying...');
                    $this->resyncPostgresSequence();
                    $replica->save();
                } else {
                    throw $e;
                }
            }

            $this->logger->scheduled($replica);

            return $this->withAccount($replica);
        });
    }

    /**
     * @param  list<int>  $taskIds
     */
    public function reorder(array $taskIds): void
    {
        if ($taskIds === []) {
            return;
        }

        $cases = [];
        $bindings = [];

        foreach ($taskIds as $index => $id) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $id;
            $bindings[] = $index + 1;
        }

        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));

        DB::update(
            sprintf(
                <<<'SQL'
                UPDATE scheduled_tasks
                SET
                    sort_order = CAST(CASE id
                        %s
                    END AS integer),
                    updated_at = ?
                WHERE id IN (%s)
                SQL,
                implode("\n", $cases),
                $placeholders,
            ),
            [
                ...$bindings,
                now(),
                ...$taskIds,
            ],
        );
    }

    public function withAccount(ScheduledTask $task): ScheduledTask
    {
        $task->load(['account' => static function ($query): void {
            $query->select('id', 'username', 'nickname', 'region', 'status')->withExists('marketServerConnections');
        }]);

        return $task;
    }

    public function isBusy(ScheduledTask $task): bool
    {
        return $task->status->isBusy();
    }

    private function isPostgresPrimaryKeyCollision(UniqueConstraintViolationException $e): bool
    {
        return DB::getDriverName() === 'pgsql' && str_contains($e->getMessage(), 'scheduled_tasks_pkey');
    }

    private function resyncPostgresSequence(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::statement("
                    SELECT setval(
                        pg_get_serial_sequence('scheduled_tasks', 'id'),
                        COALESCE((SELECT MAX(id) FROM \"scheduled_tasks\"), 1),
                        (SELECT MAX(id) IS NOT NULL FROM \"scheduled_tasks\")
                    )
                ");
            } catch (\Throwable $e) {
                Log::warning('[ScheduledTaskService] Failed to resync postgres sequence: '.$e->getMessage());
            }
        }
    }
}
