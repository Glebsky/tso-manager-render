<?php

namespace Tests\Feature;

use App\Jobs\AccountSyncJob;
use App\Jobs\ExecuteScheduledTaskJob;
use App\Jobs\MarketSyncJob;
use ReflectionClass;
use Tests\TestCase;

/**
 * FR-002: a job still running must never be redelivered to another worker,
 * i.e. retry_after > job timeout + safety margin on every used connection.
 */
class QueueTimeoutInvariantTest extends TestCase
{
    private const int SAFETY_MARGIN_SECONDS = 30;

    private const array JOBS = [
        ExecuteScheduledTaskJob::class,
        MarketSyncJob::class,
        AccountSyncJob::class,
    ];

    private const array CONNECTIONS = ['redis', 'database'];

    public function test_retry_after_exceeds_every_job_timeout_plus_margin(): void
    {
        foreach (self::CONNECTIONS as $connection) {
            $retryAfter = (int) config("queue.connections.{$connection}.retry_after");

            foreach (self::JOBS as $job) {
                $timeout = (int) (new ReflectionClass($job))->getDefaultProperties()['timeout'];

                $this->assertGreaterThan(
                    $timeout + self::SAFETY_MARGIN_SECONDS,
                    $retryAfter,
                    "queue.connections.{$connection}.retry_after ({$retryAfter}) must exceed {$job}::\$timeout ({$timeout}) + ".self::SAFETY_MARGIN_SECONDS
                );
            }
        }
    }

    public function test_task_job_is_not_retried_by_the_queue_and_fails_on_timeout(): void
    {
        $defaults = (new ReflectionClass(ExecuteScheduledTaskJob::class))->getDefaultProperties();

        $this->assertSame(1, $defaults['tries']);
        $this->assertTrue($defaults['failOnTimeout']);
    }
}
