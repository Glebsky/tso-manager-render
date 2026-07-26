<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MarketServerConnection;
use App\Models\Setting;
use App\Models\User;
use App\Services\MarketCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MarketCacheTest extends TestCase
{
    use RefreshDatabase;

    private MarketCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheService = app(MarketCacheService::class);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createConnection(string $serverId = 'ru'): MarketServerConnection
    {
        return MarketServerConnection::updateOrCreate(
            ['server_id' => $serverId],
            [
                'locale' => 'RU',
                'display_name' => strtoupper($serverId).' Server',
            ]
        );
    }

    public function test_data_version_lives_in_db_and_increments(): void
    {
        $this->createConnection('ru');

        $this->assertSame(1, $this->cacheService->dataVersion('ru'));

        $newVersion = $this->cacheService->bumpDataVersion('ru');
        $this->assertSame(2, $newVersion);
        $this->assertSame(2, $this->cacheService->dataVersion('ru'));
        $this->assertSame(2, (int) MarketServerConnection::where('server_id', 'ru')->value('data_version'));

        // The version must survive a full cache flush: it lives in the DB,
        // so `php artisan cache:clear` or a deploy cannot break invalidation.
        Cache::flush();
        $this->assertSame(2, $this->cacheService->dataVersion('ru'));
    }

    public function test_data_version_fallback_for_server_without_connection_row(): void
    {
        $this->assertSame(0, $this->cacheService->dataVersion('adhoc'));
        $this->assertSame(1, $this->cacheService->bumpDataVersion('adhoc'));
        $this->assertSame(1, $this->cacheService->dataVersion('adhoc'));
    }

    public function test_global_version_changes_when_any_server_bumps(): void
    {
        $this->createConnection('ru');
        $this->createConnection('de');

        $globalBefore = $this->cacheService->dataVersion(MarketCacheService::GLOBAL_SERVER);
        $this->cacheService->bumpDataVersion('de');
        $globalAfter = $this->cacheService->dataVersion(MarketCacheService::GLOBAL_SERVER);

        $this->assertNotSame($globalBefore, $globalAfter);
    }

    public function test_remember_uses_data_version_and_canonical_params(): void
    {
        $this->createConnection('ru');

        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return ['foo' => 'bar'];
        };

        // First call populates cache
        $res1 = $this->cacheService->remember('ru', 'goods', ['b' => 2, 'a' => 1], 300, $callback);
        $this->assertSame(1, $calls);
        $this->assertSame(['foo' => 'bar'], $res1);

        // Different param order gets canonicalized to same key
        $res2 = $this->cacheService->remember('ru', 'goods', ['a' => 1, 'b' => 2], 300, $callback);
        $this->assertSame(1, $calls);
        $this->assertSame(['foo' => 'bar'], $res2);

        // Bumping the version makes old keys unreachable: fresh data is
        // computed on the next read, no key registry involved.
        $this->cacheService->bumpDataVersion('ru');
        $res3 = $this->cacheService->remember('ru', 'goods', ['a' => 1, 'b' => 2], 300, $callback);
        $this->assertSame(2, $calls);
        $this->assertSame(['foo' => 'bar'], $res3);

        // The legacy key registry must not be recreated.
        $this->assertEmpty(Cache::get('market:keys:ru', []));
    }

    public function test_etag_changes_after_version_bump_and_time_bucket(): void
    {
        $this->createConnection('ru');
        Carbon::setTestNow('2026-07-26 10:00:00');

        $etag1 = $this->cacheService->generateETag('ru', 'analytics', ['a' => 1]);

        // Same version, same time bucket -> same ETag (304 flow works).
        $this->assertSame($etag1, $this->cacheService->generateETag('ru', 'analytics', ['a' => 1]));

        // Version bump -> new ETag.
        $this->cacheService->bumpDataVersion('ru');
        $etag2 = $this->cacheService->generateETag('ru', 'analytics', ['a' => 1]);
        $this->assertNotSame($etag1, $etag2);

        // New time bucket -> new ETag even without a version bump, so
        // time-dependent responses cannot be frozen at 304 between syncs.
        Carbon::setTestNow('2026-07-26 10:01:01');
        $etag3 = $this->cacheService->generateETag('ru', 'analytics', ['a' => 1]);
        $this->assertNotSame($etag2, $etag3);
    }

    public function test_public_market_api_returns_etag_and_304(): void
    {
        Carbon::setTestNow('2026-07-26 10:00:00');

        $response1 = $this->getJson('/api/public/market/servers');
        $response1->assertStatus(200);
        $this->assertTrue($response1->headers->has('ETag'));
        $this->assertTrue($response1->headers->has('X-Data-Version'));
        $cacheControl = $response1->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=60', $cacheControl);
        $this->assertStringContainsString('stale-while-revalidate=240', $cacheControl);

        $etag = $response1->headers->get('ETag');

        // Sending same ETag within the same time bucket returns 304
        $response2 = $this->withHeaders(['If-None-Match' => $etag])
            ->getJson('/api/public/market/servers');
        $response2->assertStatus(304);
        $cacheControl304 = $response2->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl304);
        $this->assertStringContainsString('max-age=60', $cacheControl304);
        $this->assertStringContainsString('stale-while-revalidate=240', $cacheControl304);
    }

    public function test_market_version_endpoint_returns_data_version_without_http_caching(): void
    {
        $this->createConnection('ru');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/market/version?server_id=ru');
        $response->assertStatus(200)
            ->assertJson([
                'server_id' => 'ru',
                'data_version' => 1,
            ]);

        $this->assertSame('1', $response->headers->get('X-Data-Version'));
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
    }

    public function test_admin_market_api_returns_version_headers(): void
    {
        $this->createConnection('ru');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/market/goods?server_id=ru');
        $response->assertStatus(200);
        $this->assertTrue($response->headers->has('ETag'));
        $this->assertTrue($response->headers->has('X-Data-Version'));
        $this->assertSame('1', $response->headers->get('X-Data-Version'));
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('max-age=60', $cacheControl);
        $this->assertStringContainsString('stale-while-revalidate=240', $cacheControl);
    }

    public function test_setting_cache_and_invalidation(): void
    {
        Setting::set('test_key', 'initial_val');
        $this->assertSame('initial_val', Setting::get('test_key'));

        // Direct DB update (bypassing model) to prove Setting::get is cached
        Setting::where('key', 'test_key')->update(['value' => 'db_changed_val']);
        $this->assertSame('initial_val', Setting::get('test_key'));

        // Setting::set invalidates cache
        Setting::set('test_key', 'new_val');
        $this->assertSame('new_val', Setting::get('test_key'));
    }
}
