<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for application settings updates.
 */
class UpdateSettingsRequest extends FormRequest
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
            'sync_interval' => ['required', 'integer', 'in:0,5,15,30,60'],
            'log_retention_days' => ['required', 'integer', 'in:0,7,14,30,90'],
            'combat_simulator_url' => ['nullable', 'string', 'url', 'max:2048'],
        ];
    }
}
