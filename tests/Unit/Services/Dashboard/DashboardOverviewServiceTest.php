<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Dashboard;

use App\Enums\LogLevel;
use App\Enums\TaskType;
use App\Models\Account;
use App\Models\BotLog;
use App\Models\ScheduledTask;
use App\Services\Dashboard\DashboardOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardOverviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_overview_returns_accurate_stats_and_structure(): void
    {
        Account::create([
            'username' => 'dash_user_1',
            'password' => 'secret',
            'region' => 'ru',
            'server_name' => 'Server 1',
        ]);
        Account::create([
            'username' => 'dash_user_2',
            'password' => 'secret',
            'region' => 'ru',
            'server_name' => 'Server 2',
        ]);

        ScheduledTask::create([
            'account_id' => 1,
            'task_type' => TaskType::CollectPickups,
            'is_active' => true,
            'interval_minutes' => 60,
            'payload' => [],
        ]);
        ScheduledTask::create([
            'account_id' => 1,
            'task_type' => TaskType::CollectPickups,
            'is_active' => false,
            'interval_minutes' => 60,
            'payload' => [],
        ]);

        BotLog::create([
            'account_id' => 1,
            'level' => LogLevel::Info,
            'message' => 'Info log today',
            'created_at' => now(),
        ]);
        BotLog::create([
            'account_id' => 1,
            'level' => LogLevel::Error,
            'message' => 'Error log today',
            'created_at' => now(),
        ]);

        $service = new DashboardOverviewService;
        $overview = $service->getOverview();

        $this->assertArrayHasKey('accounts', $overview);
        $this->assertArrayHasKey('logs', $overview);
        $this->assertArrayHasKey('stats', $overview);
        $this->assertArrayHasKey('meta', $overview);

        $stats = $overview['stats'];
        $this->assertSame(2, $stats['total_accounts']);
        $this->assertSame(1, $stats['active_tasks']);
        $this->assertSame(2, $stats['today_actions']);
        $this->assertSame(1, $stats['errors']);

        $this->assertNotEmpty($overview['meta']['server_time']);
    }
}
