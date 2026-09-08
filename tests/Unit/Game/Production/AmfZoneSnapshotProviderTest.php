<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Production;

use App\Models\Account;
use App\Services\Game\Production\AmfZoneSnapshotProvider;
use App\Services\TsoAmfService;
use App\Services\ZoneParserService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class AmfZoneSnapshotProviderTest extends TestCase
{
    public function test_it_preserves_existing_friends_when_updating_account_zone_data(): void
    {
        $existingFriends = [
            ['id' => 5555, 'nickname' => 'ProductionFriend'],
        ];

        $account = Mockery::mock(Account::class)->makePartial();
        $account->exists = true;
        $account->id = 42;
        $account->zone_data = ['friends' => $existingFriends];

        /** @var TsoAmfService&MockInterface $mockAmf */
        $mockAmf = Mockery::mock(TsoAmfService::class);
        $mockAmf->expects('getZone')->with($account)->andReturn('raw_amf_zone');

        /** @var ZoneParserService&MockInterface $mockZones */
        $mockZones = Mockery::mock(ZoneParserService::class);
        $mockZones->expects('parse')->with('raw_amf_zone')->andReturn([
            'errorCode' => 0,
            'buildings' => [],
        ]);

        $account->expects('update')
            ->once()
            ->with(Mockery::on(function ($attributes) use ($existingFriends) {
                $decoded = json_decode($attributes['zone_data'], true);

                return isset($decoded['friends']) && $decoded['friends'] === $existingFriends;
            }))
            ->andReturn(true);

        $provider = new AmfZoneSnapshotProvider($mockAmf, $mockZones);
        $provider->forAccount($account, forceRefresh: true);
    }
}
