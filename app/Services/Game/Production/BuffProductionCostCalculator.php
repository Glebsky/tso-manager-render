<?php

declare(strict_types=1);

namespace App\Services\Game\Production;

final readonly class BuffProductionCostCalculator
{
    public function forOrder(ProductionRecipe $recipe, int $amount = 1, int $stacks = 1): ProductionCost
    {
        $resources = [];
        foreach ($recipe->costs as $cost) {
            $res = (string) $cost['resource'];
            $count = (int) $cost['count'];
            $resources[$res] = $count * $amount * $stacks;
        }
        ksort($resources, SORT_STRING);

        return new ProductionCost(
            resources: $resources,
            durationSeconds: $recipe->durationSeconds * $amount * $stacks,
            complete: $recipe->costsKnown,
        );
    }

    /**
     * @param  list<array{recipe: ProductionRecipe, amount?: int, stacks?: int}>  $orders
     */
    public function forSequence(array $orders): ProductionCost
    {
        $total = new ProductionCost(resources: [], durationSeconds: 0, complete: true);
        foreach ($orders as $order) {
            $orderCost = $this->forOrder(
                $order['recipe'],
                $order['amount'] ?? 1,
                $order['stacks'] ?? 1,
            );
            $total = $total->plus($orderCost);
        }

        return $total;
    }
}
