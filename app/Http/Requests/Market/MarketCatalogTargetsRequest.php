<?php

declare(strict_types=1);

namespace App\Http\Requests\Market;

use App\Enums\MarketItemKind;
use Illuminate\Foundation\Http\FormRequest;

final class MarketCatalogTargetsRequest extends FormRequest
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
            'server_id' => ['nullable', 'string', 'max:50'],
            'item_id' => ['nullable', 'string', 'max:100'],
            'kind' => ['sometimes', 'string', 'in:all,resource,buff,adventure,building'],
        ];
    }

    public function serverId(): ?string
    {
        $id = $this->input('server_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function itemId(): string
    {
        return (string) $this->input('item_id', '');
    }

    public function kind(): ?MarketItemKind
    {
        $kind = (string) $this->input('kind', 'all');

        return $kind === 'all' ? null : MarketItemKind::tryFrom($kind);
    }
}
