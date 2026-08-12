<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\Zone\ZoneSnapshot;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Custom Eloquent Cast ensuring zone_data attribute always evaluates to a decoded array.
 *
 * @implements CastsAttributes<array<string, mixed>, array<string, mixed>|string|null>
 */
class ZoneDataCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return ZoneSnapshot::fromData($value)->toArray();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
