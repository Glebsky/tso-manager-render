<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property string $key
 * @property ?string $value
 */
class Setting extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    protected static function booted(): void
    {
        static::saved(static function (self $setting): void {
            Cache::forget("setting:{$setting->key}");
        });

        static::deleted(static function (self $setting): void {
            Cache::forget("setting:{$setting->key}");
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        // Wrap in a sentinel structure so existing null/empty settings don't trigger repeated DB queries.
        $cached = Cache::remember("setting:{$key}", 300, static function () use ($key): array {
            $setting = self::query()->find($key);

            return $setting !== null
                ? ['found' => true, 'value' => $setting->value]
                : ['found' => false, 'value' => null];
        });

        return $cached['found'] ? $cached['value'] : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value !== null ? (string) $value : null]
        );

        Cache::forget("setting:{$key}");
    }
}
