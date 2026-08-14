<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAccountSessionRequest extends FormRequest
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
            'dso_auth_token' => ['required', 'string'],
            'dso_auth_user' => ['required', 'string'],
            'bb_url' => ['required', 'url', 'starts_with:http://,https://'],
        ];
    }
}
