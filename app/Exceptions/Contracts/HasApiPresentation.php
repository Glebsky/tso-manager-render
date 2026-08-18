<?php

declare(strict_types=1);

namespace App\Exceptions\Contracts;

interface HasApiPresentation extends \Throwable
{
    public function userMessage(): string;

    public function httpStatus(): int;

    /**
     * @return array<string, mixed>
     */
    public function context(): array;
}
