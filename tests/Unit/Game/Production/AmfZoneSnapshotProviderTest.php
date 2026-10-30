<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Production;

use App\Models\Account;
use App\Services\Game\Production\AmfZoneSnapshotProvider;
use App\Services\Game\Session\GameClientIdentity;
use App\Services\Game\Session\GameSessionGuard;
use App\Services\Game\Session\GameSessionRecovery;
use App\Services\Game\Zone\HomeZoneRepository;
use App\Services\Game\Zone\VersionedZoneCacheInvalidator;
use App\Services\TsoAmfService;
use App\Services\TsoAuthService;
use App\Services\ZoneParserService;
use Illuminate\Support\Facades\Cache;
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

        $provider = $this->provider($mockAmf, $mockZones);
        $provider->forAccount($account, forceRefresh: true);
    }

    private function provider(TsoAmfService $amf, ZoneParserService $parser): AmfZoneSnapshotProvider
    {
        $zones = new VersionedZoneCacheInvalidator(Cache::store('array'));
        $guard = new GameSessionGuard(new GameSessionRecovery(Mockery::mock(TsoAuthService::class), $amf, $parser, $zones, new GameClientIdentity));

        return new AmfZoneSnapshotProvider(new HomeZoneRepository($amf, $parser, $guard, $zones));
    }
}
