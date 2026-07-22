<?php

declare(strict_types=1);

namespace App\Exceptions;

class FriendBuildingNotFoundException extends TaskExecutionException
{
    public function __construct(int $grid, string $friendName)
    {
        parent::__construct('error.friend_building_not_found', [
            'grid' => $grid,
            'friendName' => $friendName,
        ]);
    }
}
