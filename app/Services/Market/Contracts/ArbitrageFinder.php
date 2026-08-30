<?php

declare(strict_types=1);

namespace App\Services\Market\Contracts;

/**
 * Finds profitable trade loops among the currently active offers of a server.
 */
interface ArbitrageFinder
{
    /**
     * @return list<array<string, mixed>>
     */
    public function find(string $serverId): array;
}
