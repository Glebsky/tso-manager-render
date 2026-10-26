<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Models\Account;
use App\Models\ScheduledTask;
use App\Services\TsoAuthService;
use Illuminate\Support\Facades\Log;

/**
 * The one sequence engine (A-12, B-11).
 *
 * Runs the pending steps of a sequence task from `completed_steps` onwards,
 * persisting progress after every step with a CAS on the execution token and
 * waiting out inter-step delays with heartbeats. Used by both execution
 * paths:
 *
 *  - inline (`tso:execute-tasks`, sync scheduler mode): no budget, the run
 *    goes to the end in one process;
 *  - queue (`ExecuteScheduledTaskJob`): a time budget; when the next delay
 *    does not fit, the run is handed back to the caller, which re-dispatches
 *    a delayed job that resumes from `completed_steps`.
 *
 * Step failures are results (StepRunner), so the only exceptions escaping
 * this class are infrastructure ones (auth, database); the caller decides
 * what a failed run means for the task.
 */
final readonly class SequenceRunner
{
    /**
     * Safety margin for a single step's own duration when deciding whether
     * the next delay still fits into the budget.
     */
    public const int STEP_MARGIN_SECONDS = 10;

    public function __construct(
        private TsoAuthService $authService,
        private StepRunner $stepRunner,
        private TaskStateMachine $stateMachine,
        private DelayWaiter $delayWaiter,
    ) {}

    /**
     * @param  ?int  $budgetSeconds  work budget of this invocation; `null` = unlimited (never hands off)
     */
    public function run(ScheduledTask $task, Account $account, string $token, ?int $budgetSeconds = null): SequenceRunResult
    {
        $budgetEndsAt = $budgetSeconds === null ? null : microtime(true) + $budgetSeconds;
        $authenticated = false;

        while (true) {
            $task->refresh();

            $payload = TaskPayload::validate($task->payload, true);
            // Reject unpersistable data before authentication or game commands.
            TaskPayload::encode($payload);
            /** @var array<int, array<string, mixed>> $actions */
            $actions = array_values($payload['actions']);
            $stepResults = (array) ($payload['step_results'] ?? []);
            $index = $task->completed_steps ?? 0;
            $total = count($actions);

            // A run that starts from the beginning never inherits results of a previous run.
            if ($index === 0 && $stepResults !== []) {
                $stepResults = [];
                unset($payload['step_results']);
            }

            // Steps finished by an earlier attempt (resume after hand-off or a
            // previous failed run) are reported as "already executed".
            for ($i = 0; $i < min($index, $total); $i++) {
                if (! isset($stepResults[$i])) {
                    $stepResults[$i] = StepOutcome::alreadyDone($i, (string) $actions[$i]['task_type'])->toArray();
                }
            }

            if ($index >= $total) {
                $payload['step_results'] = $stepResults;

                return $this->finish($task, $account, $token, $actions, $payload);
            }

            // Ownership CAS (queued|running + token -> running). Losing it means the
            // run was stopped, recovered as stale or finished by someone else.
            if (! $this->stateMachine->start($task, $token, $payload)) {
                Log::info("[SequenceRunner] Task #{$task->id} start CAS failed at step ".($index + 1).'; aborting run.');

                return SequenceRunResult::aborted($this->summaryOf($actions, $stepResults));
            }

            if (! $authenticated) {
                $this->authService->ensureAuthenticated($account);
                $authenticated = true;
            }

            $action = $actions[$index];
            $outcome = $this->stepRunner->run($task, $account, $index, $action);

            $stepResults[$index] = $outcome->toArray();
            $payload['step_results'] = $stepResults;

            if (! $this->stateMachine->progress($task, $token, $index + 1, $payload)) {
                Log::info("[SequenceRunner] Task #{$task->id} progress CAS failed after step ".($index + 1).'; aborting run.');

                return SequenceRunResult::aborted($this->summaryOf($actions, $stepResults));
            }

            if ($index + 1 >= $total) {
                return $this->finish($task, $account, $token, $actions, $payload);
            }

            $delay = max(0, (int) ($action['delay_seconds'] ?? 0));
            if ($delay === 0) {
                continue;
            }

            if ($budgetEndsAt !== null && microtime(true) + $delay + self::STEP_MARGIN_SECONDS >= $budgetEndsAt) {
                return SequenceRunResult::handOff($this->summaryOf($actions, $stepResults), $delay);
            }

            // Waits with heartbeats and notices a concurrent Stop (FR-007).
            if (! $this->delayWaiter->wait($task, $token, $delay)) {
                Log::info("[SequenceRunner] Task #{$task->id} lost ownership during delay after step ".($index + 1).'; aborting run.');

                return SequenceRunResult::aborted($this->summaryOf($actions, $stepResults));
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<string, mixed>  $payload
     */
    private function finish(ScheduledTask $task, Account $account, string $token, array $actions, array $payload): SequenceRunResult
    {
        $results = StepResultCollection::fromPayload($actions, (array) ($payload['step_results'] ?? []));

        $this->stateMachine->finish(
            $task,
            $token,
            $results->finalStatus(),
            $results->formattedSummary(),
            $payload,
            $account->id,
        );

        return SequenceRunResult::finished($results->summary());
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<int|string, mixed>  $stepResults
     */
    private function summaryOf(array $actions, array $stepResults): string
    {
        return StepResultCollection::fromPayload($actions, $stepResults)->summary();
    }
}
