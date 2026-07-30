<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use App\Models\Account;
use App\Services\Tasks\BuffPayloadValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Everything that makes a scheduled task payload valid.
 *
 * The exact same ~50 lines of rules plus the sequence, buff and interval
 * checks were duplicated verbatim in ScheduledTaskController::store() and
 * ::update(). They now live in one place; the store/update requests only
 * declare that they reuse them.
 */
abstract class ScheduledTaskRequest extends FormRequest
{
    private const TASK_TYPES = 'stop_production,start_production,apply_buff,send_geologist,send_explorer,sequence';

    private const STEP_TASK_TYPES = 'stop_production,start_production,apply_buff,send_geologist,send_explorer';

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
            $this->buffRules(),
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
            'task_type' => 'required|string|in:'.self::TASK_TYPES,
            'payload' => 'required|array',
            'schedule_type' => 'required|string|in:daily,once,interval',
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
        return [
            'payload.actions' => 'required|array|min:1',
            'payload.actions.*.task_type' => 'required|string|in:'.self::STEP_TASK_TYPES,
            'payload.actions.*.payload' => 'required|array',
            'payload.actions.*.delay_seconds' => 'required|integer|min:0',
        ];
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
        if ($this->input('task_type') === 'apply_buff') {
            return ['payload.' => 'payload'];
        }

        if (! $this->isSequence()) {
            return [];
        }

        $prefixes = [];

        foreach ((array) $this->input('payload.actions', []) as $index => $action) {
            if (($action['task_type'] ?? '') === 'apply_buff') {
                $prefixes["payload.actions.{$index}.payload."] = "payload.actions.{$index}.payload";
            }
        }

        return $prefixes;
    }

    private function isSequence(): bool
    {
        return $this->input('task_type') === 'sequence';
    }

    private function validateInterval(Validator $validator): void
    {
        if ($this->input('schedule_type') !== 'interval') {
            return;
        }

        $hours = (int) $this->input('interval_hours', 0);
        $minutes = (int) $this->input('interval_minutes', 0);

        if ($hours === 0 && $minutes === 0) {
            $validator->errors()->add('interval_hours', 'Interval must be at least 1 minute.');
        }
    }

    private function validateBuffPayloads(Validator $validator): void
    {
        $prefixes = $this->buffPrefixes();

        if ($prefixes === []) {
            return;
        }

        $account = Account::findOrFail($this->input('account_id'));
        $buffValidator = app(BuffPayloadValidator::class);

        foreach ($prefixes as $prefix => $inputKey) {
            $errors = $buffValidator->validate($account, (array) $this->input($inputKey, []), $prefix);

            foreach ($errors as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($field, $message);
                }
            }

            if ($errors !== []) {
                return;
            }
        }
    }
}
