<?php

declare(strict_types=1);

namespace App\Http\Requests\Market;

use App\Enums\MarketItemKind;
use App\Services\Market\Support\PeriodResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class MarketAnalyticsRequest extends FormRequest
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
            'item_id' => ['nullable', 'string', 'max:100'],
            'target_item_id' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function serverId(): ?string
    {
        $id = $this->input('server_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function periodKey(): string
    {
        return (string) $this->input('period', PeriodResolver::DEFAULT_PERIOD);
    }

    public function kind(): ?MarketItemKind
    {
        $kind = (string) $this->input('kind', 'all');

        return $kind === 'all' ? null : MarketItemKind::tryFrom($kind);
    }

    public function itemId(): string
    {
        return (string) $this->input('item_id', '');
    }

    public function targetItemId(): string
    {
        return (string) $this->input('target_item_id', '');
    }

    public function page(): int
    {
        return (int) $this->input('page', 1);
    }

    public function limit(): int
    {
        return (int) $this->input('limit', (int) config('market.offers_page_size', 50));
    }
}
