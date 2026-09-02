<?php

declare(strict_types=1);

namespace App\Http\Requests\Logs;

use Illuminate\Foundation\Http\FormRequest;

class IndexLogRequest extends FormRequest
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
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'account_id' => ['sometimes', 'nullable', 'integer', 'exists:accounts,id'],
            'level' => ['sometimes', 'nullable', 'string'],
            'after_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    public function perPage(): int
    {
        $perPage = (int) $this->input('per_page', $this->input('limit', 100));

        return max(1, min(100, $perPage));
    }

    public function accountId(): ?int
    {
        return $this->filled('account_id') ? (int) $this->input('account_id') : null;
    }

    public function level(): ?string
    {
        return $this->filled('level') ? (string) $this->input('level') : null;
    }

    public function afterId(): ?int
    {
        $lastEventId = $this->header('Last-Event-ID');

        if ($this->filled('after_id')) {
            return (int) $this->input('after_id');
        }

        if ($lastEventId !== null && is_numeric($lastEventId)) {
            return (int) $lastEventId;
        }

        return null;
    }
}
