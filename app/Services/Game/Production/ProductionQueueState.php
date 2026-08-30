<?php

declare(strict_types=1);

namespace App\Services\Game\Production;

final readonly class ProductionQueueState
{
    /**
     * @param  list<array{
     *     type_string: string,
     *     amount: int,
     *     produced_items: int,
     *     collected_time: float,
     *     stacks: int,
     *     index: int
     * }>  $orders
     */
    public function __construct(
        public int $productionType,
        public array $orders = [],
    ) {}

    public static function empty(int $productionType): self
    {
        return new self($productionType, []);
    }

    public function used(): int
    {
        return count($this->orders);
    }

    public function hasOrders(): bool
    {
        return $this->orders !== [];
    }
}
