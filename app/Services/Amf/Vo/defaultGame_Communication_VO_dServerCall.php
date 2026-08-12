<?php

declare(strict_types=1);

namespace App\Services\Amf\Vo;

class defaultGame_Communication_VO_dServerCall
{
    public ?string $dsoAuthToken = null;

    public ?int $type = null;

    public ?int $zoneID = null;

    public int|string|null $dsoAuthUser = null;

    public mixed $data = null;

    public ?int $dsoAuthRandomClientID = null;
}
