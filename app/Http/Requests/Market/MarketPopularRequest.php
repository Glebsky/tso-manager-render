<?php

declare(strict_types=1);

namespace App\Http\Requests\Market;

use App\Enums\MarketItemKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for popular items query parameters.
 */
final class MarketPopularRequest extends FormRequest
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
            'server_id' => ['nullable', 'string', 'max:50'],
            'period' => ['nullable', 'string', Rule::in(['1d', '7d', '30d', '1y', 'all'])],
            'kind' => ['sometimes', 'string', 'in:all,resource,buff,adventure,building'],
        ];
    }

    public function serverId(): string
    {
        return (string) $this->input('server_id', (string) config('market.default_server_id'));
    }

    public function periodKey(): string
    {
        return (string) $this->input('period', '1d');
    }

    public function kind(): ?MarketItemKind
    {
        $kind = (string) $this->input('kind', 'all');

        return $kind === 'all' ? null : MarketItemKind::tryFrom($kind);
    }
}
