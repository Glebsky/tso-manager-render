<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\LogLevel;
use App\Enums\ScheduleType;
use App\Enums\TaskResultPrefix;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use PHPUnit\Framework\TestCase;

class EnumsTest extends TestCase
{
    public function test_task_status_enum_values(): void
    {
        $this->assertEquals('pending', TaskStatus::Pending->value);
        $this->assertEquals('queued', TaskStatus::Queued->value);
        $this->assertEquals('running', TaskStatus::Running->value);
        $this->assertEquals('completed', TaskStatus::Completed->value);
        $this->assertEquals('failed', TaskStatus::Failed->value);
        $this->assertEquals('paused', TaskStatus::Paused->value);
    }

    public function test_task_status_is_busy_helper(): void
    {
        $this->assertTrue(TaskStatus::Queued->isBusy());
        $this->assertTrue(TaskStatus::Running->isBusy());

        $this->assertFalse(TaskStatus::Pending->isBusy());
        $this->assertFalse(TaskStatus::Completed->isBusy());
        $this->assertFalse(TaskStatus::Failed->isBusy());
        $this->assertFalse(TaskStatus::Paused->isBusy());
    }

    public function test_task_type_enum_values(): void
    {
        $this->assertEquals('buff', TaskType::Buff->value);
        $this->assertEquals('apply_buff', TaskType::ApplyBuff->value);
        $this->assertEquals('building', TaskType::Building->value);
        $this->assertEquals('stop_production', TaskType::StopProduction->value);
        $this->assertEquals('start_production', TaskType::StartProduction->value);
        $this->assertEquals('specialist', TaskType::Specialist->value);
        $this->assertEquals('send_geologist', TaskType::SendGeologist->value);
        $this->assertEquals('send_explorer', TaskType::SendExplorer->value);
        $this->assertEquals('send_specialist', TaskType::SendSpecialist->value);
        $this->assertEquals('collect_pickups', TaskType::CollectPickups->value);
        $this->assertEquals('collect_building', TaskType::CollectBuilding->value);
        $this->assertEquals('build_mine', TaskType::BuildMine->value);
        $this->assertEquals('upgrade_mine', TaskType::UpgradeMine->value);
        $this->assertEquals('sequence', TaskType::Sequence->value);
        $this->assertEquals('trade', TaskType::Trade->value);
    }

    public function test_task_type_is_sequence_helper(): void
    {
        $this->assertTrue(TaskType::Sequence->isSequence());
        $this->assertFalse(TaskType::ApplyBuff->isSequence());
    }

    public function test_schedule_type_enum_values(): void
    {
        $this->assertEquals('once', ScheduleType::Once->value);
        $this->assertEquals('daily', ScheduleType::Daily->value);
        $this->assertEquals('interval', ScheduleType::Interval->value);
    }

    public function test_schedule_type_is_once_helper(): void
    {
        $this->assertTrue(ScheduleType::Once->isOnce());
        $this->assertFalse(ScheduleType::Daily->isOnce());
    }

    public function test_log_level_enum_values(): void
    {
        $this->assertEquals('info', LogLevel::Info->value);
        $this->assertEquals('warning', LogLevel::Warning->value);
        $this->assertEquals('error', LogLevel::Error->value);
        $this->assertEquals('success', LogLevel::Success->value);
        $this->assertEquals('debug', LogLevel::Debug->value);
    }

    public function test_task_result_prefix_enum_values(): void
    {
        $this->assertEquals('OK', TaskResultPrefix::Ok->value);
        $this->assertEquals('ERROR', TaskResultPrefix::Error->value);
        $this->assertEquals('SKIPPED', TaskResultPrefix::Skipped->value);
        $this->assertEquals('RESUMED', TaskResultPrefix::Resumed->value);
        $this->assertEquals('PARTIAL', TaskResultPrefix::Partial->value);
        $this->assertEquals('FAILED', TaskResultPrefix::Failed->value);
        $this->assertEquals('WARNING', TaskResultPrefix::Warning->value);
    }

    public function test_task_result_prefix_format_helper(): void
    {
        $this->assertEquals('OK: execution finished', TaskResultPrefix::Ok->format('execution finished'));
        $this->assertEquals('ERROR: invalid payload', TaskResultPrefix::Error->format('invalid payload'));
        $this->assertEquals('SKIPPED:', TaskResultPrefix::Skipped->format());
    }
}
