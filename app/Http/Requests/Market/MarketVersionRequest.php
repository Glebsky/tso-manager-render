<?php

declare(strict_types=1);

namespace App\Http\Requests\Market;

use Illuminate\Foundation\Http\FormRequest;

final class MarketVersionRequest extends FormRequest
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
        ];
    }

    public function serverId(): ?string
    {
        $serverId = $this->query('server_id');

        return is_string($serverId) && $serverId !== '' ? $serverId : null;
    }
}
