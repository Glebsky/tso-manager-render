<?php

declare(strict_types=1);

namespace App\Exceptions;

final class BuildingNotClickableException extends TaskExecutionException
{
    public function __construct(public readonly string $buildingName)
    {
        parent::__construct('error.building_not_clickable', ['name' => $buildingName]);
    }
}
