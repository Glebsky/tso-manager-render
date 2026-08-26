<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use App\Enums\ScheduleType;
use App\Enums\TaskType;
use App\Models\Account;
use App\Services\Tasks\BuffPayloadValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Shared validation rules and custom validators for Scheduled Task payloads.
 */
abstract class ScheduledTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(
            $this->baseRules(),
            $this->isSequence() ? $this->sequenceRules() : [],
            $this->isBuildingTask() ? $this->buildingRules() : [],
            $this->isSpecialistTask() ? $this->specialistRules() : [],
            $this->buffRules(),
            $this->pickupRules(),
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateInterval($validator);
            $this->validateBuffPayloads($validator);
        });
    }

    /**
     * Only the persistable attributes, never the nested validation helpers.
     *
     * @return array<string, mixed>
     */
    public function attributesForTask(): array
    {
        return $this->only(array_keys($this->baseRules()));
    }

    /**
     * @return array<string, mixed>
     */
    private function baseRules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'account_id' => 'required|exists:accounts,id',
            'task_type' => ['required', Rule::enum(TaskType::class)],
            'payload' => 'required|array',
            'schedule_type' => ['required', Rule::enum(ScheduleType::class)],
            'run_at_time' => 'required_if:schedule_type,daily|nullable|date_format:H:i',
            'run_at_datetime' => 'required_if:schedule_type,once|nullable|date',
            'interval_hours' => 'required_if:schedule_type,interval|nullable|integer|min:0',
            'interval_minutes' => 'required_if:schedule_type,interval|nullable|integer|min:0',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sequenceRules(): array
    {
        $rules = [
            'payload.actions' => 'required|array|min:1',
            'payload.actions.*.task_type' => ['required', Rule::enum(TaskType::class)],
            'payload.actions.*.payload' => 'required|array',
            'payload.actions.*.delay_seconds' => 'required|integer|min:0',
            'payload.actions.*.meta' => 'nullable|array',
        ];

        foreach ((array) $this->input('payload.actions', []) as $index => $action) {
            $taskType = $action['task_type'] ?? '';
            if ($taskType instanceof TaskType) {
                $taskType = $taskType->value;
            }
            if (in_array($taskType, [TaskType::StopProduction->value, TaskType::StartProduction->value, TaskType::CollectBuilding->value], true)) {
                $rules["payload.actions.{$index}.payload.grid"] = 'required|integer|min:1';
                $rules["payload.actions.{$index}.payload.building_name"] = 'nullable|string|max:255';
                $rules["payload.actions.{$index}.payload.building_raw_name"] = 'nullable|string|max:255';
                $rules["payload.actions.{$index}.payload.name"] = 'nullable|string|max:255';
                $rules["payload.actions.{$index}.payload.mode"] = 'nullable|string|in:auto,collectible,quest_trigger';
            }

            if (in_array($taskType, [TaskType::BuildMine->value, TaskType::UpgradeMine->value], true)) {
                $rules["payload.actions.{$index}.payload.grid"] = 'required|integer|min:1';
                $rules["payload.actions.{$index}.payload.deposit_name"] = 'nullable|string|max:255';
                $rules["payload.actions.{$index}.payload.building_name"] = 'nullable|string|max:255';
                $rules["payload.actions.{$index}.payload.max_level"] = 'nullable|integer|min:1|max:7';
                $rules["payload.actions.{$index}.payload.name"] = 'nullable|string|max:255';
            }
        }

        return $rules;
    }

    /**
     * Rules for building grid tasks (stop_production, start_production, collect_building, build_mine, upgrade_mine).
     *
     * @return array<string, mixed>
     */
    private function buildingRules(): array
    {
        return [
            'payload.grid' => 'required|integer|min:1',
            'payload.building_name' => 'nullable|string|max:255',
            'payload.building_raw_name' => 'nullable|string|max:255',
            'payload.deposit_name' => 'nullable|string|max:255',
            'payload.max_level' => 'nullable|integer|min:1|max:7',
            'payload.name' => 'nullable|string|max:255',
            'payload.mode' => 'nullable|string|in:auto,collectible,quest_trigger',
        ];
    }

    /**
     * Rules for specialist tasks (send_geologist, send_explorer, send_specialist).
     *
     * @return array<string, mixed>
     */
    private function specialistRules(): array
    {
        return [
            'payload.task_type' => 'nullable|integer',
            'payload.sub_task_id' => 'nullable|integer',
            'payload.unique_id1' => 'nullable|integer',
            'payload.unique_id2' => 'nullable|integer',
            'payload.specialist_type' => 'nullable|string|max:255',
            'payload.search_type' => 'nullable|string|max:255',
            'payload.specialist_name' => 'nullable|string|max:255',
            'payload.specialist_type_name' => 'nullable|string|max:255',
            'payload.sub_task_label' => 'nullable|string|max:255',
            'payload.name' => 'nullable|string|max:255',
        ];
    }

    /**
     * Shape rules for every collect_pickups payload, direct or inside a sequence.
     *
     * @return array<string, mixed>
     */
    private function pickupRules(): array
    {
        $rules = [];

        foreach ($this->pickupPrefixes() as $prefix) {
            $rules += [
                $prefix.'pickup_type' => 'nullable|in:all,normal,event',
                $prefix.'resources' => 'nullable|array',
                $prefix.'resources.*' => 'string|max:255',
                $prefix.'limit' => 'nullable|integer|min:1',
                $prefix.'delay_ms' => 'nullable|integer|min:0|max:5000',
            ];
        }

        return $rules;
    }

    /**
     * Payload prefixes that must be validated as a collect_pickups payload.
     *
     * @return array<int, string>
     */
    private function pickupPrefixes(): array
    {
        if ($this->taskTypeString() === TaskType::CollectPickups->value) {
            return ['payload.'];
        }

        if (! $this->isSequence()) {
            return [];
        }

        $prefixes = [];

        foreach ((array) $this->input('payload.actions', []) as $index => $action) {
            $taskType = $action['task_type'] ?? '';
            if ($taskType instanceof TaskType) {
                $taskType = $taskType->value;
            }
            if ($taskType === TaskType::CollectPickups->value) {
                $prefixes[] = "payload.actions.{$index}.payload.";
            }
        }

        return $prefixes;
    }

    /**
     * Shape rules for every apply_buff payload, direct or inside a sequence.
     *
     * @return array<string, mixed>
     */
    private function buffRules(): array
    {
        $rules = [];

        foreach ($this->buffPrefixes() as $prefix => $inputKey) {
            $rules += [
                $prefix.'target_scope' => 'nullable|in:self,friend',
                $prefix.'grid' => 'required|integer|min:1',
                $prefix.'unique_id1' => 'required|integer',
                $prefix.'unique_id2' => 'required|integer',
                $prefix.'amount' => 'nullable|integer|min:1',
                $prefix.'target_player_id' => 'required_if:'.$prefix.'target_scope,friend|nullable|integer|min:1',
                $prefix.'target_player_name' => 'nullable|string|max:255',
                $prefix.'buff_name' => 'nullable|string|max:255',
                $prefix.'buff_raw_name' => 'nullable|string|max:255',
                $prefix.'buff_resource_name' => 'nullable|string|max:255',
                $prefix.'building_name' => 'nullable|string|max:255',
                $prefix.'building_raw_name' => 'nullable|string|max:255',
                $prefix.'name' => 'nullable|string|max:255',
            ];
        }

        return $rules;
    }

    /**
     * Payload prefixes that must be validated as an apply_buff payload.
     *
     * @return array<string, string> prefix => payload input key
     */
    private function buffPrefixes(): array
    {
        if ($this->taskTypeString() === TaskType::ApplyBuff->value) {
            return ['payload.' => 'payload'];
        }

        if (! $this->isSequence()) {
            return [];
        }

        $prefixes = [];

        foreach ((array) $this->input('payload.actions', []) as $index => $action) {
            $taskType = $action['task_type'] ?? '';
            if ($taskType instanceof TaskType) {
                $taskType = $taskType->value;
            }
            if ($taskType === TaskType::ApplyBuff->value) {
                $prefixes["payload.actions.{$index}.payload."] = "payload.actions.{$index}.payload";
            }
        }

        return $prefixes;
    }

    private function taskTypeString(): ?string
    {
        $val = $this->input('task_type');
        if ($val instanceof TaskType) {
            return $val->value;
        }

        return is_string($val) ? $val : null;
    }

    private function scheduleTypeString(): ?string
    {
        $val = $this->input('schedule_type');
        if ($val instanceof ScheduleType) {
            return $val->value;
        }

        return is_string($val) ? $val : null;
    }

    private function isSequence(): bool
    {
        return $this->taskTypeString() === TaskType::Sequence->value;
    }

    private function isBuildingTask(): bool
    {
        return in_array($this->taskTypeString(), [
            TaskType::StopProduction->value,
            TaskType::StartProduction->value,
            TaskType::CollectBuilding->value,
            TaskType::BuildMine->value,
            TaskType::UpgradeMine->value,
        ], true);
    }

    private function isSpecialistTask(): bool
    {
        return in_array($this->taskTypeString(), [TaskType::SendGeologist->value, TaskType::SendExplorer->value, TaskType::SendSpecialist->value], true);
    }

    private function validateInterval(Validator $validator): void
    {
        if ($this->scheduleTypeString() !== ScheduleType::Interval->value) {
            return;
        }

        $hours = (int) $this->input('interval_hours', 0);
        $minutes = (int) $this->input('interval_minutes', 0);

        if ($hours === 0 && $minutes === 0) {
            $validator->errors()->add('interval_hours', 'Interval must be at least 1 minute.');
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateBuffPayloads(Validator $validator): void
    {
        $prefixes = $this->buffPrefixes();

        if ($prefixes === []) {
            return;
        }

        $accountId = $this->input('account_id');
        if (! $accountId) {
            return;
        }

        $account = Account::find((int) $accountId);
        if (! $account instanceof Account) {
            return;
        }

        $buffValidator = app(BuffPayloadValidator::class);
        $errorBag = $validator->errors();

        foreach ($prefixes as $prefix => $inputKey) {
            $errors = $buffValidator->validate($account, (array) $this->input($inputKey, []), $prefix);

            foreach ($errors as $field => $messages) {
                foreach ($messages as $message) {
                    $errorBag->add($field, $message);
                }
            }

            if ($errors !== []) {
                return;
            }
        }
    }
}
