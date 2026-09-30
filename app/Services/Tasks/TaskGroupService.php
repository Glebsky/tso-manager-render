<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\ScheduledTask;
use App\Models\TaskGroup;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TaskGroupService
{
    /**
     * @return Collection<int, TaskGroup>
     */
    public function forAccount(int $accountId): Collection
    {
        return TaskGroup::query()
            ->where('account_id', $accountId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, TaskGroup>
     */
    public function all(): Collection
    {
        return TaskGroup::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function create(int $accountId, string $name): TaskGroup
    {
        $cleanName = trim($name);
        $maxSort = TaskGroup::where('account_id', $accountId)->max('sort_order') ?? 0;

        return TaskGroup::create([
            'account_id' => $accountId,
            'name' => $cleanName,
            'sort_order' => $maxSort + 1,
        ]);
    }

    public function update(TaskGroup $group, string $newName): TaskGroup
    {
        $cleanName = trim($newName);
        $oldName = $group->name;

        if ($cleanName === $oldName) {
            return $group;
        }

        DB::transaction(function () use ($group, $oldName, $cleanName): void {
            $group->update(['name' => $cleanName]);

            ScheduledTask::where('account_id', $group->account_id)
                ->where('group_name', $oldName)
                ->update(['group_name' => $cleanName]);
        });

        return $group->fresh() ?? $group;
    }

    public function delete(TaskGroup $group): void
    {
        DB::transaction(function () use ($group): void {
            ScheduledTask::where('account_id', $group->account_id)
                ->where('group_name', $group->name)
                ->update(['group_name' => null]);

            $group->delete();
        });
    }

    /**
     * @param  list<int>  $groupIds
     */
    public function reorder(array $groupIds): void
    {
        if ($groupIds === []) {
            return;
        }

        $cases = [];
        $bindings = [];

        foreach ($groupIds as $index => $id) {
            $cases[] = 'WHEN ? THEN ?';
            $bindings[] = $id;
            $bindings[] = $index + 1;
        }

        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));

        DB::update(
            sprintf(
                <<<'SQL'
                UPDATE task_groups
                SET
                    sort_order = CAST(CASE id
                        %s
                    END AS integer),
                    updated_at = ?
                WHERE id IN (%s)
                SQL,
                implode("\n", $cases),
                $placeholders
            ),
            [...$bindings, now(), ...$groupIds]
        );
    }
}
