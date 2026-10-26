<?php

declare(strict_types=1);

namespace App\Services\Game\Commands;

use App\Services\ZoneParserService;
use Throwable;

/** A decoded AMF envelope is not necessarily a server acknowledgement. */
final readonly class CommandResponseDecoder
{
    public function __construct(private ZoneParserService $parser) {}

    public function errorCode(string $raw): ?int
    {
        try {
            $data = $this->parser->parse($raw);

            return ($data['server_response_confirmed'] ?? false) === true
                && isset($data['errorCode']) && is_int($data['errorCode'])
                    ? $data['errorCode'] : null;
        } catch (Throwable) {
            return null;
        }
    }
}
