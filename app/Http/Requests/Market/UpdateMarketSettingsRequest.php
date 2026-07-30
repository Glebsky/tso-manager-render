<?php

declare(strict_types=1);

namespace App\Http\Requests\Market;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation of the market synchronisation settings.
 *
 * The allowed intervals come from config/market.php, so the whitelist has a
 * single source of truth instead of being duplicated in a rule string.
 */
final class UpdateMarketSettingsRequest extends FormRequest
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
        /** @var list<string> $allowed */
        $allowed = (array) config('market.sync.allowed_intervals', ['5', '15', '30', '60', 'custom']);

        return [
            'sync_interval' => ['required', 'string', Rule::in($allowed)],
            'custom_interval_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function syncInterval(): string
    {
        return (string) $this->validated('sync_interval');
    }

    public function customIntervalMinutes(): ?int
    {
        $value = $this->validated('custom_interval_minutes');

        return $value !== null ? (int) $value : null;
    }
}
