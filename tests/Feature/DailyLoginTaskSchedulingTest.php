<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ScheduledTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DailyLoginTaskSchedulingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function task(array $payload = ['scope' => 'self']): array
    {
        $account = Account::create([
            'username' => 'daily-offline-test', 'password' => 'test-only',
            'region' => 'ru', 'dso_auth_user' => '123',
        ]);

        return [
            'account_id' => $account->id,
            'task_type' => 'collect_daily_login_reward',
            'schedule_type' => 'once',
            'run_at_datetime' => now()->addDay()->toIso8601String(),
            'payload' => $payload,
        ];
    }

    public function test_daily_login_task_can_be_saved_without_running_game_commands(): void
    {
        $this->postJson('/api/tasks', $this->task())->assertSuccessful();
        $task = ScheduledTask::query()->firstOrFail();
        $this->assertSame('collect_daily_login_reward', $task->task_type->value);
        $this->assertSame('self', ($task->payload ?? [])['scope'] ?? null);
    }

    public function test_daily_login_scope_cannot_be_a_friend(): void
    {
        $this->postJson('/api/tasks', $this->task(['scope' => 'friend']))
            ->assertUnprocessable()->assertJsonValidationErrors('payload.scope');
    }

    public function test_daily_login_scope_is_required(): void
    {
        $this->postJson('/api/tasks', $this->task(['unexpected' => true]))
            ->assertUnprocessable()->assertJsonValidationErrors('payload.scope');
    }

    public function test_daily_login_can_be_saved_in_a_sequence(): void
    {
        $task = $this->task();
        $task['task_type'] = 'sequence';
        $task['payload'] = ['actions' => [[
            'task_type' => 'collect_daily_login_reward',
            'payload' => ['scope' => 'self'], 'delay_seconds' => 0,
        ]]];
        $this->postJson('/api/tasks', $task)->assertSuccessful();
    }

    public function test_sequence_daily_login_cannot_target_a_friend(): void
    {
        $task = $this->task();
        $task['task_type'] = 'sequence';
        $task['payload'] = ['actions' => [[
            'task_type' => 'collect_daily_login_reward',
            'payload' => ['scope' => 'friend'], 'delay_seconds' => 0,
        ]]];
        $this->postJson('/api/tasks', $task)->assertUnprocessable()
            ->assertJsonValidationErrors('payload.actions.0.payload.scope');
    }
}
