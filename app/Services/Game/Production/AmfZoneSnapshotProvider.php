<?php

declare(strict_types=1);

namespace App\Services\Game\Production;

use App\Exceptions\GameServerErrorException;
use App\Models\Account;
use App\Services\GameErrorResolver;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;
use App\Support\Zone\ZoneSnapshot;
use Exception;

final readonly class AmfZoneSnapshotProvider implements ZoneSnapshotProviderInterface
{
    public function __construct(
        private TsoAmfService $amf,
        private ZoneParserService $parser
    ) {}

    /**
     * @throws GameServerErrorException
     * @throws Exception
     */
    public function forAccount(Account $account, bool $forceRefresh = false): ZoneSnapshot
    {
        if (! $forceRefresh && ! empty($account->zone_data)) {
            $snapshot = ZoneSnapshot::fromData($account->zone_data);
            if ($snapshot->buildingCount() !== null && $snapshot->buildingCount() > 0) {
                return $snapshot;
            }
        }

        $zoneAmf = $this->amf->getZone($account);
        $parsed = $this->parser->parse($zoneAmf);

        $errorCode = (int) ($parsed['errorCode'] ?? 0);
        if ($errorCode !== 0) {
            throw new GameServerErrorException($errorCode, GameErrorResolver::getMessage($errorCode));
        }

        if ($account->exists) {
            $account->update([
                'zone_data' => json_encode($parsed, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'last_sync_at' => now(),
                'status' => 'online',
            ]);
        }

        return new ZoneSnapshot($parsed);
    }
}
