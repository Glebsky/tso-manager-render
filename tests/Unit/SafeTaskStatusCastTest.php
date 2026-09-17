<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Casts\SafeTaskStatusCast;
use App\Enums\TaskStatus;
use App\Models\ScheduledTask;
use PHPUnit\Framework\TestCase;

class SafeTaskStatusCastTest extends TestCase
{
    private SafeTaskStatusCast $cast;

    private ScheduledTask $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cast = new SafeTaskStatusCast;
        $this->model = new ScheduledTask;
    }

    public function test_casts_known_status_correctly(): void
    {
        $this->assertSame(TaskStatus::Pending, $this->cast->get($this->model, 'status', 'pending', []));
        $this->assertSame(TaskStatus::Queued, $this->cast->get($this->model, 'status', 'queued', []));
        $this->assertSame(TaskStatus::Running, $this->cast->get($this->model, 'status', 'running', []));
        $this->assertSame(TaskStatus::Completed, $this->cast->get($this->model, 'status', 'completed', []));
        $this->assertSame(TaskStatus::Failed, $this->cast->get($this->model, 'status', 'failed', []));
        $this->assertSame(TaskStatus::Paused, $this->cast->get($this->model, 'status', 'paused', []));
        $this->assertSame(TaskStatus::Cancelled, $this->cast->get($this->model, 'status', 'cancelled', []));
    }

    public function test_casts_american_spelling_canceled_to_cancelled(): void
    {
        $this->assertSame(TaskStatus::Cancelled, $this->cast->get($this->model, 'status', 'canceled', []));
    }

    public function test_falls_back_to_pending_on_unknown_or_null_values(): void
    {
        $this->assertSame(TaskStatus::Pending, $this->cast->get($this->model, 'status', null, []));
        $this->assertSame(TaskStatus::Pending, $this->cast->get($this->model, 'status', '', []));
        $this->assertSame(TaskStatus::Pending, $this->cast->get($this->model, 'status', 'unknown_status_xyz', []));
    }

    public function test_preserves_task_status_enum_instance(): void
    {
        $this->assertSame(TaskStatus::Cancelled, $this->cast->get($this->model, 'status', TaskStatus::Cancelled, []));
    }

    public function test_serializes_status_to_string_on_set(): void
    {
        $this->assertSame('cancelled', $this->cast->set($this->model, 'status', TaskStatus::Cancelled, []));
        $this->assertSame('running', $this->cast->set($this->model, 'status', 'running', []));
        $this->assertSame('pending', $this->cast->set($this->model, 'status', null, []));
    }
}
