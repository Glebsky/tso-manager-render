<?php

declare(strict_types=1);

namespace App\Services\Game\Mines;

final readonly class DepositSnapshot
{
    public function __construct(
        public int $grid,
        public string $name,
        public int $amount,
        public int $maxAmount,
        public ?int $accessible = null,
    ) {}

    public function isDepleted(): bool
    {
        return $this->amount <= 0;
    }

    public function isAccessible(): bool
    {
        return $this->accessible === null || $this->accessible === 2;
    }
}
