<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use App\Models\TaskGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskGroupRequest extends FormRequest
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
        $group = $this->route('taskGroup');
        $accountId = $group instanceof TaskGroup ? $group->account_id : (int) $this->input('account_id');
        $groupId = $group instanceof TaskGroup ? $group->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('task_groups', 'name')
                    ->where('account_id', $accountId)
                    ->ignore($groupId),
            ],
        ];
    }

    public function groupName(): string
    {
        return trim((string) $this->input('name'));
    }
}
