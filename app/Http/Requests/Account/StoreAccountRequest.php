<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Services\TsoAuthService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'region' => ['required', 'string', Rule::in(TsoAuthService::supportedRegions())],
        ];
    }
}
