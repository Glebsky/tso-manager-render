<?php

declare(strict_types=1);

namespace App\Http\Requests\Market;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation of "connect a game account as a market server".
 *
 * Moving the rules out of the controller leaves the controller with a single
 * reason to change (SRP) and makes the contract reusable and testable.
 */
final class StoreMarketServerRequest extends FormRequest
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
            'account_id' => 'required|integer|exists:accounts,id',
        ];
    }

    public function accountId(): int
    {
        return (int) $this->validated('account_id');
    }
}
