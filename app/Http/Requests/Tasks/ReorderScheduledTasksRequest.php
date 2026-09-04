<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

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
            'task_ids.*' => ['required', 'integer', 'exists:scheduled_tasks,id'],
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
}
