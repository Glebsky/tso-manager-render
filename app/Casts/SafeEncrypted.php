<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class SafeEncrypted implements CastsAttributes
{
    /**
     * Cast the given value (decrypt safely with fallback to raw value).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (Throwable) {
            return (string) $value;
        }
    }

    /**
     * Prepare the given value for storage (encrypt plaintext, avoid double-encrypting).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            Crypt::decryptString((string) $value);

            return (string) $value;
        } catch (Throwable) {
            return Crypt::encryptString((string) $value);
        }
    }
}
