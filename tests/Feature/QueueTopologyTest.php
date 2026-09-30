<?php

namespace Tests\Feature;

use App\Jobs\AccountSyncJob;
use App\Jobs\ExecuteScheduledTaskJob;
use App\Jobs\MarketSyncJob;
use App\Models\Account;
use Tests\TestCase;

/**
 * FR-001 / ADR-004: every job queue is declared in config('game.queues') and
 * every worker (Docker and inline cron) consumes exactly that list.
 */
class QueueTopologyTest extends TestCase
{
    public function test_config_declares_all_application_queues_in_priority_order(): void
    {
        $this->assertSame(
            ['tso-tasks', 'tso-accounts', 'tso-market', 'default'],
            array_values(config('game.queues'))
        );
    }

    public function test_every_job_is_dispatched_to_a_configured_queue(): void
    {
        $account = new Account(['username' => 'topology_user']);
        $configured = array_values(config('game.queues'));

        $jobs = [
            new ExecuteScheduledTaskJob(1, 'token'),
            new MarketSyncJob($account, 'ru'),
            new AccountSyncJob($account),
        ];

        foreach ($jobs as $job) {
            $this->assertContains($job->queue, $configured, $job::class.' is dispatched to an unconsumed queue');
        }
    }

    public function test_docker_worker_default_queue_list_matches_config(): void
    {
        $compose = (string) file_get_contents(base_path('docker-compose.yml'));

        $matched = preg_match('/queue:work redis --queue=\$\{TSO_WORKER_QUEUES:-([^}]+)\}/', $compose, $matches);

        $this->assertSame(1, $matched, 'docker-compose.yml worker must use --queue=${TSO_WORKER_QUEUES:-<default>}');
        $this->assertSame(implode(',', config('game.queues')), $matches[1]);
    }
}
