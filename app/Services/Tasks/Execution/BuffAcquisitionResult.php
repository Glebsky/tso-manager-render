<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

final readonly class BuffAcquisitionResult
{
    public function __construct(
        public int $uniqueId1,
        public int $uniqueId2,
        public string $buffName = '',
    ) {}
}
