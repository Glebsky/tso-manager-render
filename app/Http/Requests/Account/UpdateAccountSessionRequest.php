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
            'dso_auth_token' => ['required_without:password', 'nullable', 'string'],
            'dso_auth_user' => ['required_with:dso_auth_token', 'nullable', 'string'],
            'bb_url' => ['required_with:dso_auth_token', 'nullable', 'url', 'starts_with:http://,https://'],
            'password' => ['required_without:dso_auth_token', 'nullable', 'string', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function sessionData(): array
    {
        return array_filter([
            'dso_auth_token' => (string) ($this->validated('dso_auth_token') ?? ''),
            'dso_auth_user' => (string) ($this->validated('dso_auth_user') ?? ''),
            'bb_url' => (string) ($this->validated('bb_url') ?? ''),
            'password' => (string) ($this->validated('password') ?? ''),
        ], static fn (string $val): bool => $val !== '');
    }
}
