<?php

declare(strict_types=1);

namespace App\Services\Tasks\Execution;

use App\Exceptions\GameServerErrorException;
use App\Services\GameErrorResolver;
use App\Services\ZoneParserService;

final readonly class GameResponseValidator
{
    public function __construct(private ZoneParserService $zoneParser) {}

    /**
     * @throws GameServerErrorException
     * @throws \Exception
     */
    public function validateAndParse(string $payload): void
    {
        $errorCode = $this->errorCode($payload);

        if ($errorCode !== 0) {
            throw new GameServerErrorException($errorCode, GameErrorResolver::getMessage($errorCode));
        }
    }

    /**
     * Game error code carried by an AMF response, `0` when the response is
     * not an AMF payload or reports no error. Decodes the payload instead of
     * scanning it for digits (FR-009: "1012" may legitimately appear in grid
     * ids, amounts or timestamps).
     *
     * @throws \Exception
     */
    public function errorCode(string $payload): int
    {
        if (! $this->isAmfPayload($payload)) {
            return 0;
        }

        $parsed = $this->zoneParser->parse($payload);

        return (int) ($parsed['errorCode'] ?? 0);
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
