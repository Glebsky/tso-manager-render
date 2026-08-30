<?php

declare(strict_types=1);

namespace App\Services\Game\Mines;

final readonly class BuildQueueBudget
{
    public function __construct(
        private ?BuildQueueSnapshot $queue,
    ) {}

    public function freeSlots(): int
    {
        if ($this->queue === null) {
            return 0;
        }

        return max(0, $this->queue->total - $this->queue->used);
    }

    public function hasFreeSlot(): bool
    {
        return $this->freeSlots() > 0;
    }
}
