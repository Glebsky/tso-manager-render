<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Exceptions\Contracts\SkippableStepExceptionInterface;

class BuildingNotBuffableException extends TaskExecutionException implements SkippableStepExceptionInterface
{
    public function __construct(string $reasonKey, array $params = [])
    {
        parent::__construct($reasonKey, $params, 422);
    }

    public static function alreadyBuffed(int $grid, string $name, ?string $activeBuff = null): self
    {
        return new self('error.building_already_buffed', [
            'grid' => $grid,
            'name' => $name,
            'active_buff' => $activeBuff ?? '',
        ]);
    }

    public static function productionStopped(int $grid, string $name): self
    {
        return new self('error.building_production_stopped', [
            'grid' => $grid,
            'name' => $name,
        ]);
    }

    public static function upgrading(int $grid, string $name): self
    {
        return new self('error.building_upgrading', [
            'grid' => $grid,
            'name' => $name,
        ]);
    }

    public static function depleted(int $grid, string $name): self
    {
        return new self('error.building_depleted', [
            'grid' => $grid,
            'name' => $name,
        ]);
    }

    public static function buildingNotFound(int $grid, ?string $scopeName = null): self
    {
        return new self('error.building_not_found', [
            'grid' => $grid,
            'scope' => $scopeName ?? '',
        ]);
    }

    public function getSkipReason(): string
    {
        return $this->getMessage();
    }
}
