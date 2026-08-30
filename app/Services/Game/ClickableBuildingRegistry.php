<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Enums\CollectibleKind;
use Illuminate\Support\Facades\Log;

final readonly class ClickableBuildingRegistry
{
    /**
     * @param  list<array<string, mixed>>  $patterns
     */
    public function __construct(private array $patterns) {}

    public function classify(string $buildingName): ?CollectibleKind
    {
        if ($buildingName === '') {
            return null;
        }

        foreach ($this->patterns as $entry) {
            $pattern = $entry['pattern'] ?? null;
            $kind = $entry['kind'] ?? 0;

            if (! is_string($pattern) || $pattern === '') {
                continue;
            }

            $matched = @preg_match($pattern, $buildingName);
            if ($matched === false) {
                Log::warning("[ClickableBuildingRegistry] Invalid regex pattern: {$pattern}");

                continue;
            }

            if ($matched === 1) {
                return CollectibleKind::tryFrom((int) $kind) ?? CollectibleKind::Normal;
            }
        }

        return null;
    }

    public function isClickable(string $buildingName): bool
    {
        return $this->classify($buildingName) !== null;
    }

    /**
     * @return list<string>
     */
    public function patterns(): array
    {
        $result = [];
        foreach ($this->patterns as $entry) {
            $pattern = $entry['pattern'] ?? null;
            if (is_string($pattern) && $pattern !== '') {
                $result[] = $pattern;
            }
        }

        return $result;
    }
}
