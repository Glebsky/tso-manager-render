<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

/**
 * Form Request for updating an existing scheduled task.
 */
final class UpdateScheduledTaskRequest extends ScheduledTaskRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // Convert base rules to 'sometimes' for update flexibility
        foreach (['name', 'account_id', 'task_type', 'payload', 'schedule_type'] as $field) {
            if (isset($rules[$field])) {
                if (is_array($rules[$field])) {
                    $rules[$field] = array_filter($rules[$field], static fn ($rule) => $rule !== 'required');
                    array_unshift($rules[$field], 'sometimes');
                } else {
                    $rules[$field] = 'sometimes|'.$rules[$field];
                }
            }
        }

        return $rules;
    }

    protected function requireFriendZoneCache(): bool
    {
        return false;
    }
}
