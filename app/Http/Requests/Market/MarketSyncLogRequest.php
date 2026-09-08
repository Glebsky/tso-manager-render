<?php

declare(strict_types=1);

namespace App\Http\Requests\Market;

use Illuminate\Foundation\Http\FormRequest;

final class MarketSyncLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'server_id' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function serverId(): ?string
    {
        $serverId = $this->query('server_id');

        return is_string($serverId) && $serverId !== '' ? $serverId : null;
    }

    public function limit(): int
    {
        $limit = $this->integer('limit', 10);

        return max(1, min(100, $limit));
    }
}
