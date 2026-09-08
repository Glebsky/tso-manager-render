<?php

declare(strict_types=1);

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

final class ClickableBuildingRequest extends FormRequest
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
        ];
    }

    public function accountId(): int
    {
        return (int) $this->validated('account_id');
    }
}
