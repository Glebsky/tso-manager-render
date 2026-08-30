<?php

declare(strict_types=1);

namespace App\Services\Game\Production;

use App\Enums\ProductionRejectionReason;

final readonly class ProductionDecision
{
    private function __construct(
        public bool $allowed,
        public ?ProductionRejectionReason $reason,
        public ?ProductionRecipe $recipe,
        public ?int $productionType,
    ) {}

    public static function allow(ProductionRecipe $recipe, int $productionType): self
    {
        return new self(true, null, $recipe, $productionType);
    }

    public static function reject(ProductionRejectionReason $reason): self
    {
        return new self(false, $reason, null, null);
    }
}
