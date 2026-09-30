<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskGroupRequest extends FormRequest
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
        $accountId = (int) $this->input('account_id');

        return [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('task_groups', 'name')->where('account_id', $accountId),
            ],
        ];
    }

    public function accountId(): int
    {
        return (int) $this->input('account_id');
    }

    public function groupName(): string
    {
        return trim((string) $this->input('name'));
    }
}
