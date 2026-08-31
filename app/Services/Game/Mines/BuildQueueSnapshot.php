<?php

declare(strict_types=1);

namespace App\Services\Game\Mines;

final readonly class BuildQueueSnapshot
{
    public function __construct(
        public int $used,
        public int $total,
    ) {}
}
