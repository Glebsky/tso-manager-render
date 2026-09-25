<?php

declare(strict_types=1);

use App\Models\ScheduledTask;
use App\Services\Tasks\Scheduling\ScheduleCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deterministic schedule (FR-006): the scheduler tick selects due tasks
     * with an indexed `next_run_at <= now` instead of evaluating every active
     * task in PHP. Additive; rollback drops the column only.
     */
    public function up(): void
    {
        Schema::table('scheduled_tasks', function (Blueprint $table) {
            $table->timestamp('next_run_at')->nullable()->after('interval_minutes');
            $table->index(['is_active', 'next_run_at'], 'idx_scheduled_tasks_active_next_run_at');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('scheduled_tasks', function (Blueprint $table) {
            $table->dropIndex('idx_scheduled_tasks_active_next_run_at');
            $table->dropColumn('next_run_at');
        });
    }

    /**
     * Derive `next_run_at` from the fields the old scheduler evaluated
     * (`last_run_at`, `created_at`, `run_at_time`, `run_at_datetime`).
     */
    private function backfill(): void
    {
        $calculator = new ScheduleCalculator;
        $now = CarbonImmutable::now();

        ScheduledTask::query()
            ->whereNull('next_run_at')
            ->orderBy('id')
            ->chunkById(200, function ($tasks) use ($calculator, $now): void {
                foreach ($tasks as $task) {
                    $next = $calculator->initialRunAt($task, $now);

                    if ($next !== null) {
                        ScheduledTask::whereKey($task->id)->update(['next_run_at' => $next]);
                    }
                }
            });
    }
};
