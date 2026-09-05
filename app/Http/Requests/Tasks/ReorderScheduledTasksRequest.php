<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use App\Models\ScheduledTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ReorderScheduledTasksRequest extends FormRequest
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
        return [
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function taskIds(): array
    {
        /** @var list<int> $taskIds */
        $taskIds = array_map('intval', (array) $this->input('task_ids', []));

        return $taskIds;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $taskIds = $this->taskIds();
            if ($taskIds === []) {
                return;
            }

            /** @var list<int> $existingIds */
            $existingIds = ScheduledTask::query()
                ->whereIn('id', $taskIds)
                ->pluck('id')
                ->all();

            $existingMap = array_flip($existingIds);

            foreach ($taskIds as $index => $id) {
                if (! isset($existingMap[$id])) {
                    $validator->errors()->add(
                        "task_ids.{$index}",
                        __('validation.exists', ['attribute' => "task_ids.{$index}"]),
                    );
                }
            }
        });
    }
}
