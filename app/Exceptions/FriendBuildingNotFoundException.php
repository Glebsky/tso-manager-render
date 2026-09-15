<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Exceptions\Contracts\SkippableStepExceptionInterface;

class FriendBuildingNotFoundException extends TaskExecutionException implements SkippableStepExceptionInterface
{
    public function __construct(int $grid, string $friendName)
    {
        parent::__construct('error.friend_building_not_found', [
            'grid' => $grid,
            'friendName' => $friendName,
        ], 404);
    }

    public function getSkipReason(): string
    {
        return $this->getMessage();
    }
}
