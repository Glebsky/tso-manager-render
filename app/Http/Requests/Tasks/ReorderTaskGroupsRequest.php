<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class ReorderTaskGroupsRequest extends FormRequest
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
            'group_ids' => ['required', 'array'],
            'group_ids.*' => ['integer', 'exists:task_groups,id'],
        ];
    }

    /**
     * @return list<int>
     */
    public function groupIds(): array
    {
        /** @var array<int, int|string> $ids */
        $ids = $this->input('group_ids', []);

        return array_values(array_map('intval', $ids));
    }
}
