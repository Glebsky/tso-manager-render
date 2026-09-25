<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Jobs\ExecuteScheduledTaskJob;
use App\Models\Account;
use App\Models\BotLog;
use App\Models\ScheduledTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * With $tries = 1 the queue calls failed() right after any exception, so
 * failed() must not overwrite a run that is already finalized or superseded.
 */
class ExecuteScheduledTaskJobFailedTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(TaskStatus $status, ?string $token): ScheduledTask
    {
        $account = Account::create([
            'username' => 'failed_job_user',
            'password' => 'secret',
            'region' => 'ru',
            'nickname' => 'failed_job_user',
        ]);

        return ScheduledTask::create([
            'account_id' => $account->id,
            'task_type' => 'stop_production',
            'payload' => ['grid' => 101],
            'schedule_type' => 'interval',
            'interval_minutes' => 10,
            'is_active' => true,
            'status' => $status,
            'execution_token' => $token,
            'last_result' => 'original result',
        ]);
    }

    public function test_failed_marks_task_failed_when_job_still_owns_the_run(): void
    {
        $task = $this->makeTask(TaskStatus::Running, 'job-token');

        (new ExecuteScheduledTaskJob($task->id, 'job-token'))->failed(new RuntimeException('timed out'));

        $task->refresh();
        $this->assertSame(TaskStatus::Failed, $task->status);
        $this->assertNull($task->execution_token);
        $this->assertSame(1, BotLog::where('account_id', $task->account_id)->count());
    }

    public function test_failed_does_not_overwrite_an_already_finalized_run(): void
    {
        $task = $this->makeTask(TaskStatus::Failed, null);

        (new ExecuteScheduledTaskJob($task->id, 'job-token'))->failed(new RuntimeException('boom'));

        $task->refresh();
        $this->assertSame(TaskStatus::Failed, $task->status);
        $this->assertSame('original result', $task->last_result);
        $this->assertSame(0, BotLog::where('account_id', $task->account_id)->count());
    }

    public function test_failed_does_not_touch_a_run_owned_by_another_token(): void
    {
        $task = $this->makeTask(TaskStatus::Running, 'newer-token');

        (new ExecuteScheduledTaskJob($task->id, 'stale-token'))->failed(new RuntimeException('boom'));

        $task->refresh();
        $this->assertSame(TaskStatus::Running, $task->status);
        $this->assertSame('newer-token', $task->execution_token);
    }
}
