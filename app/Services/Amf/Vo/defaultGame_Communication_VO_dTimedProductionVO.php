<?php

declare(strict_types=1);

namespace App\Services\Amf\Vo;

/**
 * AMF alias: defaultGame.Communication.VO.dTimedProductionVO
 * Source: client_scripts.txt:59375-59420, protocol-evidence.md §1
 */
class defaultGame_Communication_VO_dTimedProductionVO
{
    public ?object $uniqueId = null;

    public int $playerId = 0;

    public ?int $productionType = null;

    public ?string $type_string = null;

    public ?int $amount = null;

    public int $producedItems = 0;

    public float $collectedTime = 0.0;

    public int $modifiedProductionAdder = 0;

    public float $modifiedProductionMultiplier = 1.0;

    public int $modifiedInstantFinishCostAdder = 0;

    public float $modifiedInstantFinishCostMultiplier = 1.0;

    public ?int $stacks = null;

    public int $index = 0;

    public ?int $buildingGrid = null;
}
