<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Exceptions\GameServerErrorException;
use App\Services\GameErrorResolver;
use App\Services\ZoneParserService;

final class GameResponseValidator
{
    public function __construct(private readonly ZoneParserService $zoneParser) {}

    public function validateAndParse(string $payload): void
    {
        if (! $this->isAmfPayload($payload)) {
            return;
        }

        $parsed = $this->zoneParser->parse($payload);
        $errorCode = (int) ($parsed['errorCode'] ?? 0);
        if ($errorCode !== 0) {
            $errorMsg = GameErrorResolver::getMessage($errorCode);

            throw new GameServerErrorException($errorCode, $errorMsg);
        }
    }

    private function isAmfPayload(string $payload): bool
    {
        if ($payload === '') {
            return false;
        }

        return str_contains($payload, "\x00")
            || (str_starts_with(trim($payload), '{') && str_contains($payload, 'errorCode'))
            || str_contains($payload, '_amf_response');
    }
}
