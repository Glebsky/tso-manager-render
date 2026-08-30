<?php

declare(strict_types=1);

namespace App\Http\Requests\Market;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation of a market server connection update.
 */
final class UpdateMarketServerRequest extends FormRequest
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
            'account_id' => 'nullable|integer|exists:accounts,id',
            'sync_status' => 'nullable|string|in:not_configured,syncing,connected,error,disabled',
        ];
    }
}
