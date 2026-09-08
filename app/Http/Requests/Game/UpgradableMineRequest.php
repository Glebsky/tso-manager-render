<?php

declare(strict_types=1);

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

final class UpgradableMineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'integer', 'min:1'],
            'refresh' => ['nullable', 'boolean'],
            'max_level' => ['nullable', 'integer', 'min:2', 'max:7'],
        ];
    }

    public function accountId(): int
    {
        return (int) $this->validated('account_id');
    }

    public function refresh(): bool
    {
        return $this->boolean('refresh');
    }

    public function maxLevel(): ?int
    {
        $level = $this->validated('max_level');

        return $level !== null ? (int) $level : null;
    }
}
